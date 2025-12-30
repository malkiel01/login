/**
 * File: dashboard/dashboards/cemeteries/forms/sortable-sections.js
 * Version: 1.0.0
 * Description: קוד משותף לסקשנים ניתנים לגרירה בכל הטפסים
 *
 * שימוש:
 * 1. הוסף את הסקריפט לטופס
 * 2. קרא ל-SortableSections.init(containerId, storagePrefix)
 *
 * דוגמה:
 * SortableSections.init('graveSortableSections', 'graveCard');
 * SortableSections.init('customerSortableSections', 'customerCard');
 */

window.SortableSections = {

    /**
     * אתחול מלא של סקשנים ניתנים לגרירה
     * @param {string} containerId - ID של המיכל (ללא #)
     * @param {string} storagePrefix - קידומת ל-localStorage (למשל: 'graveCard', 'customerCard')
     */
    init: function(containerId, storagePrefix) {
        const container = document.getElementById(containerId);
        if (!container) {
            return;
        }


        // אתחול כל הרכיבים
        this.initToggle(container, storagePrefix);
        this.initSortable(container, storagePrefix);
        this.initResize(container, storagePrefix);
        this.loadSavedState(container, storagePrefix);
    },

    /**
     * פונקציית צימצום/הרחבה גלובלית
     */
    toggleSection: function(btn, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        const section = btn.closest('.sortable-section');
        if (!section) return;

        section.classList.toggle('collapsed');

        // זיהוי הקונטיינר וה-prefix
        const container = section.closest('.sortable-sections');
        const storagePrefix = this.getStoragePrefix(container);
        const sectionId = section.dataset.section;

        // שמירה ב-localStorage
        const storageKey = storagePrefix + 'Collapsed';
        const collapsedSections = JSON.parse(localStorage.getItem(storageKey) || '[]');

        if (section.classList.contains('collapsed')) {
            if (!collapsedSections.includes(sectionId)) {
                collapsedSections.push(sectionId);
            }
        } else {
            const index = collapsedSections.indexOf(sectionId);
            if (index > -1) {
                collapsedSections.splice(index, 1);
            }
        }
        localStorage.setItem(storageKey, JSON.stringify(collapsedSections));
    },

    /**
     * זיהוי prefix לפי container
     */
    getStoragePrefix: function(container) {
        if (!container) return 'form';
        const id = container.id || '';
        if (id.includes('grave')) return 'graveCard';
        if (id.includes('customer')) return 'customerCard';
        if (id.includes('purchase')) return 'purchaseCard';
        if (id.includes('burial')) return 'burialCard';
        return 'form';
    },

    /**
     * אתחול כפתורי צימצום/הרחבה
     */
    initToggle: function(container, storagePrefix) {
        const buttons = container.querySelectorAll('.section-toggle-btn');
        const self = this;

        buttons.forEach(function(btn) {
            // הסר onclick ישן אם קיים
            btn.removeAttribute('onclick');

            // פונקציה לטיפול בלחיצה
            function handleToggleClick(e) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();


                const section = btn.closest('.sortable-section');
                if (section) {
                    section.classList.toggle('collapsed');

                    // שמירה ב-localStorage
                    const sectionId = section.dataset.section;
                    const storageKey = storagePrefix + 'Collapsed';
                    const collapsedSections = JSON.parse(localStorage.getItem(storageKey) || '[]');

                    if (section.classList.contains('collapsed')) {
                        if (!collapsedSections.includes(sectionId)) {
                            collapsedSections.push(sectionId);
                        }
                    } else {
                        const index = collapsedSections.indexOf(sectionId);
                        if (index > -1) {
                            collapsedSections.splice(index, 1);
                        }
                    }
                    localStorage.setItem(storageKey, JSON.stringify(collapsedSections));
                }
            }

            // הוסף event listeners עם capture phase כדי לתפוס לפני SortableJS
            btn.addEventListener('click', handleToggleClick, true);
            btn.addEventListener('mousedown', function(e) {
                e.stopPropagation();
            }, true);
            btn.addEventListener('touchstart', function(e) {
                e.stopPropagation();
            }, { passive: false, capture: true });
            btn.addEventListener('touchend', function(e) {
                e.preventDefault();
                e.stopPropagation();
                handleToggleClick(e);
            }, { passive: false, capture: true });
        });

    },

    /**
     * אתחול SortableJS לגרירה
     */
    initSortable: function(container, storagePrefix) {
        const self = this;

        // בדוק אם SortableJS קיים
        if (typeof Sortable === 'undefined') {
            // טען דינמית
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js';
            script.onload = function() {
                self.setupSortable(container, storagePrefix);
            };
            document.head.appendChild(script);
            return;
        }

        this.setupSortable(container, storagePrefix);
    },

    /**
     * הגדרת SortableJS
     */
    setupSortable: function(container, storagePrefix) {
        new Sortable(container, {
            animation: 150,
            handle: '.section-drag-handle',
            filter: '.section-toggle-btn',
            preventOnFilter: false,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            delay: 150,
            delayOnTouchOnly: true,
            onEnd: function(evt) {
                const order = Array.from(container.children)
                    .filter(el => el.classList.contains('sortable-section'))
                    .map(el => el.dataset.section);
                localStorage.setItem(storagePrefix + 'SectionOrder', JSON.stringify(order));
            }
        });

        // טען סדר שמור
        const savedOrder = localStorage.getItem(storagePrefix + 'SectionOrder');
        if (savedOrder) {
            try {
                const order = JSON.parse(savedOrder);
                order.forEach(function(sectionId) {
                    const section = container.querySelector('.sortable-section[data-section="' + sectionId + '"]');
                    if (section) {
                        container.appendChild(section);
                    }
                });
            } catch (e) {}
        }

    },

    /**
     * אתחול ידיות שינוי גובה
     */
    initResize: function(container, storagePrefix) {
        const sections = container.querySelectorAll('.sortable-section');

        sections.forEach(function(section) {
            const resizeHandle = section.querySelector('.section-resize-handle');
            const content = section.querySelector('.section-content');

            if (!resizeHandle || !content) return;

            let isResizing = false;
            let startY = 0;
            let startHeight = 0;
            const sectionId = section.dataset.section;
            const minHeight = 50;
            const maxHeight = 600;

            // טען גובה שמור
            const savedHeights = JSON.parse(localStorage.getItem(storagePrefix + 'SectionHeights') || '{}');
            if (savedHeights[sectionId]) {
                content.style.height = savedHeights[sectionId] + 'px';
                content.style.maxHeight = savedHeights[sectionId] + 'px';
            }

            function startResize(clientY) {
                isResizing = true;
                startY = clientY;
                startHeight = content.offsetHeight;
                section.classList.add('resizing');
                document.body.style.cursor = 'ns-resize';
            }

            function doResize(clientY) {
                if (!isResizing) return;
                const deltaY = clientY - startY;
                let newHeight = startHeight + deltaY;
                newHeight = Math.max(minHeight, Math.min(maxHeight, newHeight));
                content.style.height = newHeight + 'px';
                content.style.maxHeight = newHeight + 'px';
            }

            function endResize() {
                if (!isResizing) return;
                isResizing = false;
                section.classList.remove('resizing');
                document.body.style.cursor = '';
                const savedHeights = JSON.parse(localStorage.getItem(storagePrefix + 'SectionHeights') || '{}');
                savedHeights[sectionId] = content.offsetHeight;
                localStorage.setItem(storagePrefix + 'SectionHeights', JSON.stringify(savedHeights));
            }

            // Mouse events
            resizeHandle.addEventListener('mousedown', function(e) {
                e.preventDefault();
                startResize(e.clientY);
            });

            document.addEventListener('mousemove', function(e) {
                doResize(e.clientY);
            });

            document.addEventListener('mouseup', function() {
                endResize();
            });

            // Touch events
            resizeHandle.addEventListener('touchstart', function(e) {
                e.preventDefault();
                if (e.touches.length === 1) {
                    startResize(e.touches[0].clientY);
                }
            }, { passive: false });

            document.addEventListener('touchmove', function(e) {
                if (isResizing && e.touches.length === 1) {
                    e.preventDefault();
                    doResize(e.touches[0].clientY);
                }
            }, { passive: false });

            document.addEventListener('touchend', function() {
                endResize();
            });
        });

    },

    /**
     * טעינת מצב שמור (collapsed sections)
     */
    loadSavedState: function(container, storagePrefix) {
        const collapsedSections = JSON.parse(localStorage.getItem(storagePrefix + 'Collapsed') || '[]');
        collapsedSections.forEach(function(sectionId) {
            const section = container.querySelector('.sortable-section[data-section="' + sectionId + '"]');
            if (section) {
                section.classList.add('collapsed');
            }
        });
    }
};

// הגדרת פונקציה גלובלית לתאימות עם onclick בקוד HTML
window.toggleSection = function(btn, event) {
    SortableSections.toggleSection(btn, event);
};

