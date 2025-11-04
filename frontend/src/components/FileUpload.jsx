import { useRef, useState } from "react"
import { Upload, File, X, AlertCircle, Loader2, CheckCircle2 } from "lucide-react"
import { Button } from "@/components/ui/button"
import { Card, CardContent } from "@/components/ui/card"
import { cn } from "@/lib/utils"

const API_BASE_URL = "http://localhost:8000/api"

export function FileUpload({ onUploadSuccess }) {
  const [isDragging, setIsDragging] = useState(false)
  const [selectedFile, setSelectedFile] = useState(null)
  const [error, setError] = useState(null)
  const [uploading, setUploading] = useState(false)
  const [uploadResult, setUploadResult] = useState(null)
  const fileInputRef = useRef(null)

  const isValidCSV = (file) => {
    const validTypes = ["text/csv", "application/vnd.ms-excel"]
    const validExtensions = [".csv", ".txt"]
    const fileExtension = file.name.toLowerCase().substring(file.name.lastIndexOf("."))
    
    // Check file size (50MB max)
    const maxSize = 50 * 1024 * 1024 // 50MB in bytes
    if (file.size > maxSize) {
      setError("File size exceeds 50MB limit")
      return false
    }
    
    return (
      validTypes.includes(file.type) ||
      validExtensions.includes(fileExtension)
    )
  }

  const handleDragOver = (e) => {
    e.preventDefault()
    setIsDragging(true)
  }

  const handleDragLeave = (e) => {
    e.preventDefault()
    setIsDragging(false)
  }

  const handleDrop = (e) => {
    e.preventDefault()
    setIsDragging(false)
    const files = e.dataTransfer.files
    if (files.length > 0) {
      handleFileSelection(files[0])
    }
  }

  const handleFileInput = (e) => {
    const files = e.target.files
    if (files.length > 0) {
      handleFileSelection(files[0])
    }
  }

  const handleFileSelection = (file) => {
    setError(null)
    setUploadResult(null)
    
    if (!isValidCSV(file)) {
      if (!error) {
        setError("Please upload a CSV or TXT file only")
      }
      setSelectedFile(null)
      if (fileInputRef.current) {
        fileInputRef.current.value = ""
      }
      return
    }
    
    setSelectedFile(file)
  }

  const handleRemove = () => {
    setSelectedFile(null)
    setError(null)
    setUploadResult(null)
    if (fileInputRef.current) {
      fileInputRef.current.value = ""
    }
  }

  const handleUpload = async () => {
    if (!selectedFile) {
      setError("Please select a file first")
      return
    }

    try {
      setUploading(true)
      setError(null)
      setUploadResult(null)

      const formData = new FormData()
      formData.append("files[]", selectedFile)

      const response = await fetch(`${API_BASE_URL}/files/upload`, {
        method: "POST",
        body: formData,
      })

      const result = await response.json()

      if (!response.ok) {
        // Handle different error responses
        if (result.status === "error") {
          if (result.errors) {
            // Validation errors (422)
            const errorMessages = Object.values(result.errors).flat()
            setError(errorMessages.join(", ") || result.message)
          } else {
            // Other errors (400, etc.)
            setError(result.message || "Upload failed")
          }
        } else {
          setError("Upload failed. Please try again.")
        }
        return
      }

      if (result.status === "success") {
        setUploadResult(result)
        // Pass file_header_id to parent for polling
        if (onUploadSuccess && result.data && result.data.length > 0) {
          onUploadSuccess(result.data[0].file_header_id)
        }
        // Clear selected file after successful upload
        setTimeout(() => {
          setSelectedFile(null)
          setUploadResult(null)
          if (fileInputRef.current) {
            fileInputRef.current.value = ""
          }
          // Refresh file list
          if (onUploadSuccess) {
            onUploadSuccess()
          }
        }, 3000)
      } else {
        setError(result.message || "Upload failed")
      }
    } catch (err) {
      setError(err.message || "Network error. Please check your connection.")
      console.error("Upload error:", err)
    } finally {
      setUploading(false)
    }
  }

  const handleClick = (e) => {
    // Only trigger if clicking directly on the drop zone, not on buttons or other elements
    if (e.target === e.currentTarget || e.target.closest('button') === null) {
      fileInputRef.current?.click()
    }
  }

  return (
    <Card>
      <CardContent className="p-6">
        <div
          className={cn(
            "border-2 border-dashed rounded-lg p-8 text-center transition-colors cursor-pointer",
            isDragging
              ? "border-primary bg-primary/5"
              : "border-muted-foreground/25 hover:border-primary/50",
            selectedFile && "border-primary bg-primary/5"
          )}
          onDragOver={handleDragOver}
          onDragLeave={handleDragLeave}
          onDrop={handleDrop}
          onClick={handleClick}
        >
          <input
            ref={fileInputRef}
            type="file"
            accept=".csv,.txt,text/csv"
            className="hidden"
            onChange={handleFileInput}
          />
          {selectedFile ? (
            <div className="space-y-4">
              <div className="flex items-center justify-center gap-2">
                <File className="size-8 text-primary" />
                <div className="flex-1 text-left max-w-md">
                  <p className="font-medium text-sm">{selectedFile.name}</p>
                  <p className="text-xs text-muted-foreground">
                    {(selectedFile.size / 1024 / 1024).toFixed(2)} MB
                  </p>
                </div>
                {!uploading && (
                  <Button
                    variant="ghost"
                    size="icon"
                    onClick={(e) => {
                      e.stopPropagation()
                      handleRemove()
                    }}
                  >
                    <X className="size-4" />
                  </Button>
                )}
              </div>
              {uploadResult ? (
                <div className="space-y-2">
                  <div className="flex items-center justify-center gap-2 text-sm text-green-600 dark:text-green-400">
                    <CheckCircle2 className="size-4" />
                    <span>{uploadResult.message || "File uploaded successfully!"}</span>
                  </div>
                  {uploadResult.data && uploadResult.data.length > 0 && (
                    <div className="text-xs text-muted-foreground space-y-1">
                      {uploadResult.data.map((fileResult, index) => (
                        <div key={index} className="text-left">
                          <p className="font-medium">{fileResult.file_name}</p>
                          <p className={cn(
                            fileResult.status === "processing" 
                              ? "text-blue-600 dark:text-blue-400" 
                              : fileResult.status === "skipped"
                              ? "text-yellow-600 dark:text-yellow-400"
                              : "text-green-600 dark:text-green-400"
                          )}>
                            Status: {fileResult.status === "processing" 
                              ? "Processing in background" 
                              : fileResult.status === "skipped"
                              ? "Already uploaded (idempotent)"
                              : fileResult.status}
                          </p>
                          {fileResult.message && (
                            <p className="text-xs mt-1">{fileResult.message}</p>
                          )}
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              ) : (
                <div className="flex items-center gap-2 justify-center">
                  <Button
                    onClick={(e) => {
                      e.stopPropagation()
                      handleClick()
                    }}
                    variant="outline"
                    size="sm"
                    disabled={uploading}
                  >
                    Change File
                  </Button>
                  <Button
                    onClick={(e) => {
                      e.stopPropagation()
                      handleUpload()
                    }}
                    disabled={uploading}
                    size="sm"
                  >
                    {uploading ? (
                      <>
                        <Loader2 className="size-4 mr-2 animate-spin" />
                        Uploading...
                      </>
                    ) : (
                      "Upload File"
                    )}
                  </Button>
                </div>
              )}
              {error && (
                <div className="flex items-center justify-center gap-2 text-sm text-destructive">
                  <AlertCircle className="size-4" />
                  <span>{error}</span>
                </div>
              )}
            </div>
          ) : (
            <div className="space-y-4">
              <div className="flex justify-center">
                <Upload className="size-12 text-muted-foreground" />
              </div>
              <div className="space-y-2">
                <p className="text-sm font-medium">
                  Drag and drop your file here
                </p>
                <p className="text-xs text-muted-foreground">or</p>
                <Button 
                  onClick={(e) => {
                    e.stopPropagation()
                    fileInputRef.current?.click()
                  }} 
                  variant="outline"
                >
                  Browse Files
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">
                CSV or TXT files only (Max 50MB)
              </p>
              {error && (
                <div className="flex items-center justify-center gap-2 text-sm text-destructive mt-2">
                  <AlertCircle className="size-4" />
                  <span>{error}</span>
                </div>
              )}
            </div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

