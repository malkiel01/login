window.PurchasedCardTemplate = {
    render: function(record) {
        const initials = this.getInitials(record.c_firstName, record.c_lastName);
        
        return `
            <div class="result-card">
                <div class="image-placeholder">
                    <span class="initials">${initials}</span>
                </div>
                <div class="card-content">
                    <div class="name">${record.c_firstName || ''} ${record.c_lastName || ''}</div>
                    <div class="parents">רוכש הקבר</div>
                    <div class="purchase-info">
                        ${record.p_price ? `<span class="price">מחיר: ₪${record.p_price}</span>` : ''}
                        ${record.p_deedNum ? `<span class="deed">שטר: ${record.p_deedNum}</span>` : ''}
                        ${record.p_purchaseStatus_display ? `<span class="status">${record.p_purchaseStatus_display}</span>` : ''}
                    </div>
                    <div class="location">
                        <span class="location-icon">📍</span>
                        <span>
                            ${record.cemeteryNameHe || ''}
                            ${record.blockNameHe ? `, גוש ${record.blockNameHe}` : ''}
                            ${record.plotNameHe ? `, חלקה ${record.plotNameHe}` : ''}
                            ${record.graveNameHe ? `, קב222ר ${record.graveNameHe}` : ''}
                        </span>
                    </div>
                </div>
            </div>
        `;
    },
    
    getInitials: function(firstName, lastName) {
        const first = firstName ? firstName.charAt(0) : '';
        const last = lastName ? lastName.charAt(0) : '';
        return (first + last) || '?';
    }
};