// window.DeceasedTableTemplate = {
//     render: function(results, displayLabels, returnFields) {
//         const headers = this.renderHeaders(displayLabels, returnFields);
//         const rows = this.renderRows(results, returnFields);
        
//         return `
//             <div class="results-table-container">
//                 <table class="result-table">
//                     <thead>${headers}</thead>
//                     <tbody>${rows}</tbody>
//                 </table>
//             </div>
//         `;
//     },
    
//     renderHeaders: function(displayLabels, returnFields) {
//         const headerCells = returnFields.map(field => 
//             `<th>${displayLabels[field] || field}</th>`
//         ).join('');
//         return `<tr>${headerCells}</tr>`;
//     },
    
//     renderRows: function(results, returnFields) {
//         return results.map(record => {
//             const cells = returnFields.map(field => {
//                 let value = record[field];
                
//                 if (record[field + '_display']) {
//                     value = record[field + '_display'];
//                 } else if (field.includes('Date') && value) {
//                     value = this.formatDate(value);
//                 } else {
//                     value = value || '-';
//                 }
                
//                 return `<td>${value}</td>`;
//             }).join('');
            
//             return `<tr>${cells}</tr>`;
//         }).join('');
//     },
    
//     formatDate: function(dateStr) {
//         if (!dateStr) return '-----';
//         try {
//             const date = new Date(dateStr);
//             if (isNaN(date.getTime())) return '-----';
//             return date.toLocaleDateString('he-IL');
//         } catch {
//             return '-----';
//         }
//     }
// };