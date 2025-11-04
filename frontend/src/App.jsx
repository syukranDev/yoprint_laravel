import { useState } from "react"
import { FileUpload } from "./components/FileUpload"
import { FileList } from "./components/FileList"

function App() {
  const [uploadedFile, setUploadedFile] = useState(null)

  const handleFileSelect = (file) => {
    setUploadedFile(file)
    console.log("File selected:", file.name)
  }

  return (
    <div className="min-h-screen bg-background">
      <div className="mx-auto max-w-4xl py-8 px-4">
        <div className="space-y-8">
          <div className="text-center space-y-2">
            <h1 className="text-3xl font-bold tracking-tight">File Upload</h1>
            <p className="text-muted-foreground">
              Upload your files and manage them easily
            </p>
          </div>

          <FileUpload onFileSelect={handleFileSelect} />

          <FileList />
        </div>
      </div>
    </div>
  )
}

export default App
