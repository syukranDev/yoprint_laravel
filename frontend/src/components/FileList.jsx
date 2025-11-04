import { useState, useEffect, useCallback } from "react"
import { File, Download, Trash2, Calendar, Loader2 } from "lucide-react"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

const API_BASE_URL = "http://localhost:8000/api"

function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  })
}

function getStatusBadge(status) {
  const statusConfig = {
    completed: {
      label: "Completed",
      className: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
    },
    completed_with_errors: {
      label: "Completed with Errors",
      className: "bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400",
    },
    processing: {
      label: "Processing",
      className: "bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400",
    },
    failed: {
      label: "Failed",
      className: "bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400",
    },
    skipped: {
      label: "Skipped",
      className: "bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400",
    },
  }

  const config = statusConfig[status] || statusConfig.processing
  return (
    <span
      className={cn(
        "px-2 py-1 rounded-full text-xs font-medium",
        config.className
      )}
    >
      {config.label}
    </span>
  )
}

export function FileList({ refreshTrigger }) {
  const [files, setFiles] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [fileStatuses, setFileStatuses] = useState({}) // Store detailed status for each file

  const fetchFiles = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      const response = await fetch(`${API_BASE_URL}/files`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
        },
      })

      if (!response.ok) {
        throw new Error(`Failed to fetch files: ${response.statusText}`)
      }

      const result = await response.json()

      if (result.status === "success") {
        // Map API response to component format
        const mappedFiles = result.data.map((file) => ({
          id: file.id,
          name: file.file_name,
          status: file.status,
          createdAt: file.created_at,
        }))
        setFiles(mappedFiles)
      } else {
        throw new Error("Invalid response format")
      }
    } catch (err) {
      setError(err.message)
      console.error("Error fetching files:", err)
    } finally {
      setLoading(false)
    }
  }, [])

  // Fetch detailed status for a file
  const fetchFileStatus = useCallback(async (fileId) => {
    try {
      const response = await fetch(`${API_BASE_URL}/files/${fileId}/status`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
        },
      })

      if (!response.ok) {
        if (response.status === 404) {
          return null // File not found, might have been deleted
        }
        throw new Error(`Failed to fetch status: ${response.statusText}`)
      }

      const result = await response.json()

      if (result.status === "success") {
        return result.data
      }
      return null
    } catch (err) {
      console.error("Error fetching file status:", err)
      return null
    }
  }, [])

  // Poll status for processing files
  useEffect(() => {
    const processingFiles = files.filter((file) => file.status === "processing")
    
    if (processingFiles.length === 0) {
      return
    }

    // Fetch status for all processing files immediately
    const fetchAllStatuses = async () => {
      const statusPromises = processingFiles.map(async (file) => {
        const status = await fetchFileStatus(file.id)
        return { fileId: file.id, status }
      })

      const statuses = await Promise.all(statusPromises)
      const statusMap = {}
      let shouldRefresh = false
      
      statuses.forEach(({ fileId, status }) => {
        if (status) {
          statusMap[fileId] = status
          // Check if status changed from processing to completed/failed
          const currentFile = files.find((f) => f.id === fileId)
          if (
            currentFile &&
            currentFile.status === "processing" &&
            (status.status === "completed" ||
              status.status === "completed_with_errors" ||
              status.status === "failed")
          ) {
            shouldRefresh = true
          }
        }
      })
      
      setFileStatuses((prev) => ({ ...prev, ...statusMap }))
      
      // Refresh file list if any file completed processing
      if (shouldRefresh) {
        setTimeout(() => {
          fetchFiles()
        }, 500)
      }
    }

    fetchAllStatuses()

    // Set up polling interval (every 2 seconds)
    const interval = setInterval(() => {
      fetchAllStatuses()
    }, 2000)

    return () => clearInterval(interval)
  }, [files, fetchFileStatus, fetchFiles])

  useEffect(() => {
    fetchFiles()
  }, [fetchFiles, refreshTrigger])

  const handleDownload = (file) => {
    console.log("Download file:", file.name)
    // Implement download logic here
  }

  const handleDelete = (file) => {
    console.log("Delete file:", file.name)
    // Implement delete logic here
    // After deletion, refresh the file list
    // fetchFiles()
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Uploaded Files</CardTitle>
      </CardHeader>
      <CardContent>
        {loading ? (
          <div className="text-center py-8 text-muted-foreground">
            <Loader2 className="size-12 mx-auto mb-4 opacity-50 animate-spin" />
            <p>Loading files...</p>
          </div>
        ) : error ? (
          <div className="text-center py-8 text-destructive">
            <File className="size-12 mx-auto mb-4 opacity-50" />
            <p className="mb-2">Error loading files</p>
            <p className="text-sm text-muted-foreground">{error}</p>
            <Button
              variant="outline"
              size="sm"
              onClick={fetchFiles}
              className="mt-4"
            >
              Retry
            </Button>
          </div>
        ) : files.length === 0 ? (
          <div className="text-center py-8 text-muted-foreground">
            <File className="size-12 mx-auto mb-4 opacity-50" />
            <p>No files uploaded yet</p>
          </div>
        ) : (
          <div className="space-y-2">
            {files.map((file) => {
              const detailedStatus = fileStatuses[file.id]
              const isProcessing = file.status === "processing"
              const showProgress = isProcessing && detailedStatus

              return (
                <div
                  key={file.id}
                  className={cn(
                    "p-4 rounded-lg border transition-colors",
                    "hover:bg-accent/50"
                  )}
                >
                  <div className="flex items-center gap-4">
                    <div className="flex items-center justify-center size-10 rounded-md bg-muted">
                      {isProcessing ? (
                        <Loader2 className="size-5 text-blue-500 animate-spin" />
                      ) : (
                        <File className="size-5 text-muted-foreground" />
                      )}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="font-medium text-sm truncate">{file.name}</p>
                      {showProgress && (
                        <div className="mt-2 space-y-1">
                          <div className="flex items-center justify-between text-xs text-muted-foreground">
                            <span>
                              {detailedStatus.processed_rows} / {detailedStatus.total_rows} rows
                            </span>
                            <span>{detailedStatus.progress_percentage?.toFixed(1) || 0}%</span>
                          </div>
                          <div className="w-full bg-muted rounded-full h-1.5">
                            <div
                              className="bg-blue-500 h-1.5 rounded-full transition-all duration-300"
                              style={{
                                width: `${detailedStatus.progress_percentage || 0}%`,
                              }}
                            />
                          </div>
                          {detailedStatus.successful_rows !== undefined && (
                            <div className="flex items-center gap-4 text-xs text-muted-foreground">
                              <span className="text-green-600 dark:text-green-400">
                                ✓ {detailedStatus.successful_rows} successful
                              </span>
                              {detailedStatus.failed_rows > 0 && (
                                <span className="text-red-600 dark:text-red-400">
                                  ✗ {detailedStatus.failed_rows} failed
                                </span>
                              )}
                            </div>
                          )}
                          {detailedStatus.error_message && (
                            <p className="text-xs text-destructive mt-1">
                              {detailedStatus.error_message}
                            </p>
                          )}
                        </div>
                      )}
                    </div>
                    <div className="flex items-center gap-4">
                      {getStatusBadge(file.status)}
                      <span className="flex items-center gap-1 text-xs text-muted-foreground">
                        <Calendar className="size-3" />
                        {formatDate(file.createdAt)}
                      </span>
                    </div>
                    <div className="flex items-center gap-2">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleDownload(file)}
                      >
                        <Download className="size-4" />
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => handleDelete(file)}
                      >
                        <Trash2 className="size-4 text-destructive" />
                      </Button>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

