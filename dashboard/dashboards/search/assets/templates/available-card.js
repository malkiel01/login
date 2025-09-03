window.AvailableCardTemplate = {
    render: function(record) {
        return `
            <div class="result-card available-grave">
                <div class="image-placeholder">
                    <span class="initials">🏞️</span>
                </div>
                <div class="card-content">
                    <div class="name">קבר פנוי #${record.graveNameHe || record.graveId}</div>
                    <div class="status-badge ${this.getStatusClass(record.graveStatus)}">
                        ${record.graveStatus_display || 'פנוי'}
                    </div>
                    <div class="location">
                        <span class="location-icon">📍</span>
                        <span>
                            ${record.cemeteryNameHe || ''}
                            ${record.blockNameHe ? `, גוש ${record.blockNameHe}` : ''}
                            ${record.plotNameHe ? `, חלקה ${record.plotNameHe}` : ''}
                            ${record.areaGraveNameHe ? `, אזור ${record.areaGraveNameHe}` : ''}
                        </span>
                    </div>
                    <div class="grave-id">
                        מזהה: ${record.graveId || '-'}
                    </div>
                </div>
            </div>
        `;
    },
    
    getStatusClass: function(status) {
        switch(status) {
            case '1': return 'status-available';
            case '2': return 'status-occupied';
            case '3': return 'status-reserved';
            case '4': return 'status-purchased';
            default: return 'status-unknown';
        }
    }
};