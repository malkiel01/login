// /**
//  * PDF Text Printer API - Usage Examples
//  * דוגמאות לשימוש ב-API
//  */

// // ==============================================
// // Example 1: Basic Usage - Hebrew Text
// // ==============================================
// async function printHebrewText() {
//     const data = {
//         filename: "https://example.com/document.pdf",
//         language: "he",
//         values: [
//             {
//                 text: "שלום עולם",
//                 x: 100,  // Distance from right edge for Hebrew
//                 y: 100
//             },
//             {
//                 text: "זוהי דוגמה בעברית",
//                 x: 100,
//                 y: 120
//             },
//             {
//                 text: "תאריך: 04/09/2025",
//                 x: 100,
//                 y: 140,
//                 fontSize: 10,
//                 color: [0, 0, 255]  // Blue color (RGB)
//             }
//         ]
//     };

//     try {
//         const response = await fetch('https://yourserver.com/process-pdf.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//             },
//             body: JSON.stringify(data)
//         });

//         const result = await response.json();
        
//         if (result.success) {
//             console.log('Success!', result.message);
//             console.log('Download URL:', result.download_url);
            
//             // Automatically download the file
//             window.open(result.download_url, '_blank');
//         } else {
//             console.error('Error:', result.error);
//         }
//     } catch (error) {
//         console.error('Network error:', error);
//     }
// }

// // ==============================================
// // Example 2: English Text with Custom Styling
// // ==============================================
// async function printEnglishText() {
//     const data = {
//         filename: "https://example.com/invoice.pdf",
//         language: "en",
//         values: [
//             {
//                 text: "Invoice #12345",
//                 x: 50,   // Distance from left edge for English
//                 y: 50,
//                 fontSize: 18,
//                 fontStyle: "B",  // Bold
//                 color: [0, 0, 0]  // Black
//             },
//             {
//                 text: "Date: September 4, 2025",
//                 x: 50,
//                 y: 70,
//                 fontSize: 12
//             },
//             {
//                 text: "Customer: John Doe",
//                 x: 50,
//                 y: 85,
//                 fontSize: 12
//             },
//             {
//                 text: "Total: $1,234.56",
//                 x: 50,
//                 y: 100,
//                 fontSize: 14,
//                 color: [255, 0, 0]  // Red
//             }
//         ]
//     };

//     const response = await fetch('https://yourserver.com/process-pdf.php', {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/json',
//         },
//         body: JSON.stringify(data)
//     });

//     const result = await response.json();
//     console.log(result);
// }

// // ==============================================
// // Example 3: Create New PDF (No existing file)
// // ==============================================
// async function createNewPDF() {
//     const data = {
//         filename: "",  // Empty filename = create new PDF
//         language: "he",
//         values: [
//             {
//                 text: "מסמך חדש",
//                 x: 100,
//                 y: 50
//             },
//             {
//                 text: "נוצר באמצעות המערכת",
//                 x: 100,
//                 y: 70
//             }
//         ]
//     };

//     const response = await fetch('https://yourserver.com/process-pdf.php', {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/json',
//         },
//         body: JSON.stringify(data)
//     });

//     const result = await response.json();
//     console.log(result);
// }

// // ==============================================
// // Example 4: Form Filling Example
// // ==============================================
// async function fillForm() {
//     const formData = {
//         name: "יוסי כהן",
//         id: "123456789",
//         address: "רחוב הרצל 10, תל אביב",
//         date: new Date().toLocaleDateString('he-IL')
//     };

//     const data = {
//         filename: "https://example.com/form-template.pdf",
//         language: "he",
//         values: [
//             {
//                 text: formData.name,
//                 x: 150,
//                 y: 100
//             },
//             {
//                 text: formData.id,
//                 x: 150,
//                 y: 120
//             },
//             {
//                 text: formData.address,
//                 x: 150,
//                 y: 140
//             },
//             {
//                 text: formData.date,
//                 x: 150,
//                 y: 160
//             }
//         ]
//     };

//     try {
//         const response = await fetch('https://yourserver.com/process-pdf.php', {
//             method: 'POST',
//             headers: {
//                 'Content-Type': 'application/json',
//             },
//             body: JSON.stringify(data)
//         });

//         const result = await response.json();
        
//         if (result.success) {
//             // Display success message
//             alert('הטופס מולא בהצלחה!');
            
//             // Download the filled form
//             const link = document.createElement('a');
//             link.href = result.download_url;
//             link.download = 'filled_form.pdf';
//             link.click();
//         }
//     } catch (error) {
//         alert('שגיאה במילוי הטופס: ' + error.message);
//     }
// }

// // ==============================================
// // Example 5: Batch Processing
// // ==============================================
// async function batchProcess(files) {
//     const results = [];
    
//     for (const file of files) {
//         const data = {
//             filename: file.url,
//             language: file.language || "en",
//             values: file.values
//         };
        
//         try {
//             const response = await fetch('https://yourserver.com/process-pdf.php', {
//                 method: 'POST',
//                 headers: {
//                     'Content-Type': 'application/json',
//                 },
//                 body: JSON.stringify(data)
//             });
            
//             const result = await response.json();
//             results.push({
//                 original: file.url,
//                 result: result
//             });
//         } catch (error) {
//             results.push({
//                 original: file.url,
//                 error: error.message
//             });
//         }
//     }
    
//     return results;
// }

// // ==============================================
// // Example 6: Dynamic Position Calculation
// // ==============================================
// function calculatePosition(text, alignment = 'left', pageWidth = 595) {
//     // A4 page width in points = 595
//     const margin = 50;
//     const textWidth = text.length * 6; // Approximate width
    
//     let x;
//     switch(alignment) {
//         case 'center':
//             x = (pageWidth - textWidth) / 2;
//             break;
//         case 'right':
//             x = pageWidth - margin - textWidth;
//             break;
//         case 'left':
//         default:
//             x = margin;
//     }
    
//     return x;
// }

// // Usage example with dynamic positioning
// async function printWithAlignment() {
//     const data = {
//         filename: "https://example.com/document.pdf",
//         language: "en",
//         values: [
//             {
//                 text: "Left Aligned Text",
//                 x: calculatePosition("Left Aligned Text", 'left'),
//                 y: 100
//             },
//             {
//                 text: "Center Aligned Text",
//                 x: calculatePosition("Center Aligned Text", 'center'),
//                 y: 120
//             },
//             {
//                 text: "Right Aligned Text",
//                 x: calculatePosition("Right Aligned Text", 'right'),
//                 y: 140
//             }
//         ]
//     };
    
//     // Send to server...
// }

// // ==============================================
// // Example 7: Table Creation
// // ==============================================
// function createTable(startX, startY, data, columnWidths) {
//     const values = [];
//     const rowHeight = 20;
//     const fontSize = 10;
    
//     let currentY = startY;
    
//     // Add headers
//     const headers = Object.keys(data[0]);
//     headers.forEach((header, colIndex) => {
//         values.push({
//             text: header,
//             x: startX + columnWidths.slice(0, colIndex).reduce((a, b) => a + b, 0),
//             y: currentY,
//             fontSize: fontSize,
//             fontStyle: 'B'
//         });
//     });
    
//     currentY += rowHeight;
    
//     // Add data rows
//     data.forEach(row => {
//         headers.forEach((header, colIndex) => {
//             values.push({
//                 text: String(row[header]),
//                 x: startX + columnWidths.slice(0, colIndex).reduce((a, b) => a + b, 0),
//                 y: currentY,
//                 fontSize: fontSize
//             });
//         });
//         currentY += rowHeight;
//     });
    
//     return values;
// }

// // Usage
// async function printTable() {
//     const tableData = [
//         { Name: "John", Age: 30, City: "New York" },
//         { Name: "Jane", Age: 25, City: "London" },
//         { Name: "Bob", Age: 35, City: "Paris" }
//     ];
    
//     const data = {
//         filename: "https://example.com/document.pdf",
//         language: "en",
//         values: createTable(50, 100, tableData, [100, 50, 100])
//     };
    
//     // Send to server...
// }

// // ==============================================
// // Example 8: Error Handling
// // ==============================================
// class PDFPrinterClient {
//     constructor(apiUrl) {
//         this.apiUrl = apiUrl;
//     }
    
//     async print(data) {
//         // Validate input
//         if (!data.values || data.values.length === 0) {
//             throw new Error('No values to print');
//         }
        
//         // Validate each value
//         for (const value of data.values) {
//             if (!value.text) {
//                 throw new Error('Text is required for each value');
//             }
//             if (typeof value.x !== 'number' || typeof value.y !== 'number') {
//                 throw new Error('X and Y must be numbers');
//             }
//             if (value.x < 0 || value.y < 0) {
//                 throw new Error('X and Y must be positive');
//             }
//         }
        
//         try {
//             const response = await fetch(this.apiUrl, {
//                 method: 'POST',
//                 headers: {
//                     'Content-Type': 'application/json',
//                 },
//                 body: JSON.stringify(data),
//                 timeout: 30000  // 30 second timeout
//             });
            
//             if (!response.ok) {
//                 throw new Error(`HTTP error! status: ${response.status}`);
//             }
            
//             const result = await response.json();
            
//             if (!result.success) {
//                 throw new Error(result.error || 'Unknown error occurred');
//             }
            
//             return result;
            
//         } catch (error) {
//             // Log error for debugging
//             console.error('PDF Printer Error:', error);
            
//             // Re-throw with user-friendly message
//             if (error.name === 'NetworkError' || error.name === 'TypeError') {
//                 throw new Error('Network error: Could not connect to server');
//             } else if (error.name === 'TimeoutError') {
//                 throw new Error('Request timeout: Server took too long to respond');
//             } else {
//                 throw error;
//             }
//         }
//     }
    
//     // Helper method to test connection
//     async testConnection() {
//         try {
//             const response = await fetch(this.apiUrl + '?test=1');
//             return response.ok;
//         } catch {
//             return false;
//         }
//     }
// }

// // Usage
// const pdfPrinter = new PDFPrinterClient('https://yourserver.com/process-pdf.php');

// // Test connection before sending data
// pdfPrinter.testConnection().then(connected => {
//     if (connected) {
//         console.log('Server is reachable');
//         // Proceed with printing...
//     } else {
//         console.error('Cannot connect to server');
//     }
// });

// // ==============================================
// // Example 9: Progress Tracking for Multiple PDFs
// // ==============================================
// async function processMultiplePDFs(pdfList, onProgress) {
//     const total = pdfList.length;
//     const results = [];
    
//     for (let i = 0; i < total; i++) {
//         const pdf = pdfList[i];
        
//         // Update progress
//         if (onProgress) {
//             onProgress({
//                 current: i + 1,
//                 total: total,
//                 percentage: ((i + 1) / total) * 100,
//                 currentFile: pdf.filename
//             });
//         }
        
//         try {
//             const response = await fetch('https://yourserver.com/process-pdf.php', {
//                 method: 'POST',
//                 headers: {
//                     'Content-Type': 'application/json',
//                 },
//                 body: JSON.stringify(pdf)
//             });
            
//             const result = await response.json();
//             results.push(result);
//         } catch (error) {
//             results.push({
//                 success: false,
//                 error: error.message,
//                 filename: pdf.filename
//             });
//         }
//     }
    
//     return results;
// }

// // Usage with progress bar
// const pdfsToProcess = [
//     { filename: "file1.pdf", language: "he", values: [...] },
//     { filename: "file2.pdf", language: "en", values: [...] },
//     { filename: "file3.pdf", language: "he", values: [...] }
// ];

// processMultiplePDFs(pdfsToProcess, (progress) => {
//     console.log(`Processing ${progress.current}/${progress.total} (${progress.percentage}%)`);
//     console.log(`Current file: ${progress.currentFile}`);
    
//     // Update progress bar UI
//     document.querySelector('#progressBar').style.width = progress.percentage + '%';
// });

// // ==============================================
// // Export functions for use in other modules
// // ==============================================
// export {
//     printHebrewText,
//     printEnglishText,
//     createNewPDF,
//     fillForm,
//     batchProcess,
//     calculatePosition,
//     createTable,
//     PDFPrinterClient,
//     processMultiplePDFs
// };