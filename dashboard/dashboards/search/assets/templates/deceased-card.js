// window.DeceasedCardTemplate = {
//     render: function(record) {
//         const initials = this.getInitials(record.c_firstName, record.c_lastName);
        
//         return `
//             <div class="result-card">
//                 <div class="image-placeholder">
//                     <span class="initials">${initials}</span>
//                 </div>
//                 <div class="card-content">
//                     <div class="name">${record.c_firstName || ''} ${record.c_lastName || ''}</div>
//                     ${record.c_numId ? `<div class="id-number">ת.ז: ${record.c_numId}</div>` : ''}
//                     <div class="parents">
//                         ${record.c_nameFather ? `בן ${record.c_nameFather}` : ''}
//                         ${record.c_nameMother ? ` ו${record.c_nameMother}` : ''}
//                     </div>
//                     <div class="dates">
//                         ${record.c_dateBirth ? `נולד: ${this.formatDate(record.c_dateBirth)}` : ''}
//                         </br>
//                         ${record.b_dateDeath ? `נפטר: ${this.formatDate(record.b_dateDeath)}` : ''}
//                         ${record.b_dateBurial ? `נקבר: ${this.formatDate(record.b_dateBurial)}` : ''}
//                     </div>
//                     <div class="location">
//                         <span class="location-icon">📍</span>
//                         <span>
//                             ${record.cemeteryNameHe || ''}
//                             ${record.blockNameHe ? `, גוש ${record.blockNameHe}` : ''}
//                             ${record.plotNameHe ? `, חלקה ${record.plotNameHe}` : ''}
//                             ${record.lineNameHe ? `, שורה ${record.lineNameHe}` : ''}
//                             ${record.graveNameHe ? `, קבר ${record.graveNameHe}` : ''}
//                         </span>
//                     </div>
//                     ${record.c_comment ? `<div class="comment">הערות: ${record.c_comment}</div>` : ''}
//                 </div>
//             </div>
//         `;
//     },
    
//     getInitials: function(firstName, lastName) {
//         const first = firstName ? firstName.charAt(0) : '';
//         const last = lastName ? lastName.charAt(0) : '';
//         return (first + last) || '?';
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
window.DeceasedCardTemplate = {
    render: function(record) {
        const initials = this.getInitials(record.c_firstName, record.c_lastName);
        
        // הוסף data attribute עם המידע המלא
        return `
            <div class="result-card clickable" onclick="DeceasedModal.open(${JSON.stringify(record).replace(/"/g, '&quot;')})">
                <div class="image-placeholder">
                    <span class="initials">${initials}</span>
                </div>
                <div class="card-content">
                    <div class="name">${record.c_firstName || ''} ${record.c_lastName || ''}</div>
                    ${record.c_numId ? `<div class="id-number">ת.ז: ${record.c_numId}</div>` : ''}
                    <div class="parents">
                        ${record.c_nameFather ? `בן ${record.c_nameFather}` : ''}
                        ${record.c_nameMother ? ` ו${record.c_nameMother}` : ''}
                    </div>
                    <div class="dates">
                        ${record.c_dateBirth ? `נולד: ${this.formatDate(record.c_dateBirth)}` : ''}
                        <br>
                        ${record.b_dateDeath ? `נפטר: ${this.formatDate(record.b_dateDeath)}` : ''}
                    </div>
                    <div class="location">
                        <span class="location-icon">📍</span>
                        <span>
                            ${record.cemeteryNameHe || ''}
                            ${record.graveNameHe ? `, קבר ${record.graveNameHe}` : ''}
                        </span>
                    </div>
                    <div class="click-hint">לחץ לפרטים מלאים</div>
                </div>
            </div>
        `;
    },
    
    getInitials: function(firstName, lastName) {
        const first = firstName ? firstName.charAt(0) : '';
        const last = lastName ? lastName.charAt(0) : '';
        return (first + last) || '?';
    },
    
    formatDate: function(dateStr) {
        if (!dateStr) return '-----';
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return '-----';
            return date.toLocaleDateString('he-IL');
        } catch {
            return '-----';
        }
    }
};