import { useState, useEffect } from "react"
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
    processed: {
      label: "Processed",
      className: "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400",
    },
    processed_with_errors: {
      label: "Processed with Errors",
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

export function FileList() {
  const [files, setFiles] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  useEffect(() => {
    fetchFiles()
  }, [])

  const fetchFiles = async () => {
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
  }

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
            {files.map((file) => (
              <div
                key={file.id}
                className={cn(
                  "flex items-center gap-4 p-4 rounded-lg border transition-colors",
                  "hover:bg-accent/50"
                )}
              >
                <div className="flex items-center justify-center size-10 rounded-md bg-muted">
                  <File className="size-5 text-muted-foreground" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="font-medium text-sm truncate">{file.name}</p>
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
            ))}
          </div>
        )}
      </CardContent>
    </Card>
  )
}

