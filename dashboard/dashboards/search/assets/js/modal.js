window.DeceasedModal = {
    currentRecord: null,
    
    init() {
        this.setupEventListeners();
    },
    
    setupEventListeners() {
        // סגירת מודאל בלחיצה על X
        const closeBtn = document.querySelector('.modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close());
        }
        
        // סגירת מודאל בלחיצה מחוץ לחלון
        const modal = document.getElementById('deceased-modal');
        if (modal) {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    this.close();
                }
            });
        }
        
        // סגירה ב-ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen()) {
                this.close();
            }
        });
    },
    
    open(record) {
        this.currentRecord = record;
        const modal = document.getElementById('deceased-modal');
        const modalBody = document.getElementById('modal-body');
        
        if (!modal || !modalBody) return;
        
        modalBody.innerHTML = this.renderModalContent(record);
        modal.classList.add('show');
        document.body.style.overflow = 'hidden'; // מניעת גלילה ברקע
    },
    
    close() {
        const modal = document.getElementById('deceased-modal');
        if (modal) {
            modal.classList.remove('show');
            document.body.style.overflow = ''; // החזרת גלילה
            this.currentRecord = null;
        }
    },
    
    isOpen() {
        const modal = document.getElementById('deceased-modal');
        return modal && modal.classList.contains('show');
    },
    
    renderModalContent(record) {
        const initials = this.getInitials(record.c_firstName, record.c_lastName);
        
        return `
            <div class="deceased-modal-card">
            ררררררר
                <div class="deceased-modal-header">
                    <div class="deceased-modal-image">
                        ${initials}
                    </div>
                </div>
                
                <div class="deceased-modal-body">
                    <!-- שם ופרטים בסיסיים -->
                    <div class="deceased-name-section">
                        <div class="deceased-main-name">
                            ${record.c_firstName || ''} ${record.c_lastName || ''}
                        </div>
                        ${record.c_numId ? `
                            <div class="deceased-id">
                                <strong>ת.ז:</strong> ${record.c_numId}
                            </div>
                        ` : ''}
                        <div class="deceased-parents">
                            ${record.c_nameFather ? `בן ${record.c_nameFather}` : ''}
                            ${record.c_nameMother ? ` ו${record.c_nameMother}` : ''}
                        </div>
                    </div>
                    
                    <!-- תאריכים -->
                    <div class="info-section">
                        <div class="info-section-title">
                            📅 תאריכים
                        </div>
                        <div class="info-grid">
                            ${record.c_dateBirth ? `
                                <div class="info-item">
                                    <span class="info-label">תאריך לידה</span>
                                    <span class="info-value">${this.formatDate(record.c_dateBirth)}</span>
                                </div>
                            ` : ''}
                            ${record.b_dateDeath ? `
                                <div class="info-item">
                                    <span class="info-label">תאריך פטירה</span>
                                    <span class="info-value">
                                        ${this.formatDate(record.b_dateDeath)}
                                        ${record.b_timeDeath ? ` | ${record.b_timeDeath}` : ''}
                                    </span>
                                </div>
                            ` : ''}
                            ${record.b_dateBurial ? `
                                <div class="info-item">
                                    <span class="info-label">תאריך קבורה</span>
                                    <span class="info-value">
                                        ${this.formatDate(record.b_dateBurial)}
                                        ${record.b_timeBurial ? ` | ${record.b_timeBurial}` : ''}
                                    </span>
                                </div>
                            ` : ''}
                            ${this.calculateAge(record.c_dateBirth, record.b_dateDeath) ? `
                                <div class="info-item">
                                    <span class="info-label">גיל בפטירה</span>
                                    <span class="info-value">${this.calculateAge(record.c_dateBirth, record.b_dateDeath)}</span>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <!-- מיקום -->
                    <div class="location-section">
                        <div class="info-section-title">
                            📍 מיקום הקבר
                        </div>
                        <div class="location-details">
                            ${record.cemeteryNameHe ? `
                                <span class="location-item">
                                    <strong>בית עלמין:</strong> ${record.cemeteryNameHe}
                                </span>
                            ` : ''}
                            ${record.blockNameHe ? `
                                <span class="location-item">
                                    <strong>גוש:</strong> ${record.blockNameHe}
                                </span>
                            ` : ''}
                            ${record.plotNameHe ? `
                                <span class="location-item">
                                    <strong>חלקה:</strong> ${record.plotNameHe}
                                </span>
                            ` : ''}
                            ${record.lineNameHe ? `
                                <span class="location-item">
                                <strong>שורה:</strong> ${record.lineNameHe}
                                </span>
                                ` : ''}
                            ${record.areaGraveNameHe ?
                                (record.graveNameHe && record.graveNameHe === record.areaGraveNameHe) ?
                                `
                                <span class="location-item">
                                    <strong>קבר:</strong> ${record.areaGraveNameHe}
                                </span>
                                `
                                :
                                (record.graveNameHe) 
                                `
                                <span class="location-item">
                                    <strong>אחוזת קבר:</strong> ${record.areaGraveNameHe}
                                </span>
                                <span class="location-item">
                                    <strong>קבר:</strong> ${record.graveNameHe}
                                </span>
                                ` : 'חסר פרטי קבר'
                            }
                        </div>
                    </div>
                    היייייייי
                    <!-- הערות -->
                    ${record.c_comment ? `
                        <div class="comment-section">
                            <div class="info-section-title">
                                📝 הערות
                            </div>
                            <div class="comment-text">
                                ${record.c_comment}
                            </div>
                        </div>
                    ` : ''}
                </div>
            </div>
        `;
    },
    
    getInitials(firstName, lastName) {
        const first = firstName ? firstName.charAt(0) : '';
        const last = lastName ? lastName.charAt(0) : '';
        return (first + last) || '?';
    },
    
    formatDate(dateStr) {
        if (!dateStr) return '-----';
        try {
            const date = new Date(dateStr);
            if (isNaN(date.getTime())) return '-----';
            return date.toLocaleDateString('he-IL');
        } catch {
            return '-----';
        }
    },
    
    calculateAge(birthDate, deathDate) {
        if (!birthDate || !deathDate) return null;
        
        try {
            const birth = new Date(birthDate);
            const death = new Date(deathDate);
            
            if (isNaN(birth.getTime()) || isNaN(death.getTime())) return null;
            
            let age = death.getFullYear() - birth.getFullYear();
            const monthDiff = death.getMonth() - birth.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && death.getDate() < birth.getDate())) {
                age--;
            }
            
            return age + ' שנים';
        } catch {
            return null;
        }
    }
};