// window.AvailableTableTemplate = {
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
//                 } else {
//                     value = value || '-';
//                 }
                
//                 // הוסף סימון צבע לסטטוס
//                 if (field === 'graveStatus' || field === 'graveStatus_display') {
//                     const statusClass = this.getStatusClass(record.graveStatus);
//                     return `<td><span class="status-badge ${statusClass}">${value}</span></td>`;
//                 }
                
//                 return `<td>${value}</td>`;
//             }).join('');
            
//             return `<tr>${cells}</tr>`;
//         }).join('');
//     },
    
//     getStatusClass: function(status) {
//         switch(status) {
//             case '1': return 'status-available';
//             case '2': return 'status-occupied';
//             case '3': return 'status-reserved';
//             case '4': return 'status-purchased';
//             default: return 'status-unknown';
//         }
//     }
// };