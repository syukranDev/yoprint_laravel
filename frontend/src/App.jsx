import { useState } from "react"
import { FileUpload } from "./components/FileUpload"
import { FileList } from "./components/FileList"

function App() {
  const [refreshTrigger, setRefreshTrigger] = useState(0)

  const handleUploadSuccess = (fileHeaderId) => {
    // Trigger refresh by updating the refresh trigger
    // fileHeaderId is optional - can be used for immediate status polling if needed
    setRefreshTrigger((prev) => prev + 1)
  }

  return (
    <div className="min-h-screen bg-background">
      <div className="mx-auto max-w-4xl py-8 px-4">
        <div className="space-y-8">
          <div className="text-center space-y-2">
            <h1 className="text-3xl font-bold tracking-tight">File Upload</h1>
            <p className="text-muted-foreground">
              Upload your CSV files and manage them easily
            </p>
          </div>

          <FileUpload onUploadSuccess={handleUploadSuccess} />

          <FileList refreshTrigger={refreshTrigger} />
        </div>
      </div>
    </div>
  )
}

export default App
