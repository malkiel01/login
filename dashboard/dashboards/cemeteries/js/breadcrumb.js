/**
 * Breadcrumb Management System
 * File: /dashboard/dashboards/cemeteries/js/breadcrumb.js
 */

const BreadcrumbManager = {
    
    // Configuration
    config: {
        homeUrl: '/dashboard/',
        homeTitle: 'דף ראשי',
        homeIcon: '🏠',
        separator: '›',
        containerSelector: '#breadcrumb'
    },
    
    // Hierarchy structure
    hierarchy: {
        cemetery: {
            name: 'בתי עלמין',
            icon: '🏛️',
            url: '/dashboard/dashboards/cemeteries/',
            plural: 'בתי עלמין'
        },
        block: {
            name: 'גוש',
            icon: '📦',
            parent: 'cemetery',
            plural: 'גושים'
        },
        plot: {
            name: 'חלקה',
            icon: '📋',
            parent: 'block',
            plural: 'חלקות'
        },
        areaGrave: {
            name: 'אחוזת קבר',
            icon: '🏘️',
            parent: 'plot',
            plural: 'אחוזות קבר'
        },
        grave: {
            name: 'קבר',
            icon: '⚰️',
            parent: 'areaGrave',
            plural: 'קברים'
        }
    },
    
    // Current breadcrumb path
    currentPath: [],
    
    /**
     * Initialize breadcrumb
     */
    init() {
        this.reset();
        this.bindEventHandlers();
    },
    
    /**
     * Reset breadcrumb to home
     */
    reset() {
        this.currentPath = [{
            type: 'home',
            name: this.config.homeTitle,
            icon: this.config.homeIcon,
            url: this.config.homeUrl,
            clickable: true
        }];
        this.render();
    },
    
    /**
     * Update breadcrumb path
     */
    update(items) {
        this.currentPath = [{
            type: 'home',
            name: this.config.homeTitle,
            icon: this.config.homeIcon,
            url: this.config.homeUrl,
            clickable: true
        }];
        
        // Build path from items
        const order = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
        
        for (let type of order) {
            if (items[type]) {
                const hierarchyItem = this.hierarchy[type];
                
                // Add level name for cemetery
                if (type === 'cemetery') {
                    this.currentPath.push({
                        type: 'level',
                        name: hierarchyItem.plural,
                        icon: hierarchyItem.icon,
                        url: hierarchyItem.url,
                        clickable: true
                    });
                }
                
                // Add specific item
                this.currentPath.push({
                    type: type,
                    id: items[type].id,
                    name: items[type].name,
                    icon: hierarchyItem.icon,
                    clickable: true,
                    data: items[type]
                });
            }
        }
        
        // Mark last item as not clickable
        if (this.currentPath.length > 1) {
            this.currentPath[this.currentPath.length - 1].clickable = false;
        }
        
        this.render();
    },
    
    /**
     * Render breadcrumb HTML
     */
    // render() {
    //     const container = document.querySelector(this.config.containerSelector);
    //     if (!container) return;
        
    //     let html = '';
        
    //     this.currentPath.forEach((item, index) => {
    //         // Add separator (except for first item)
    //         if (index > 0) {
    //             html += `<span class="breadcrumb-separator">${this.config.separator}</span>`;
    //         }
            
    //         // Render item
    //         if (item.clickable) {
    //             const isHome = item.type === 'home';
    //             html += `
    //                 <a href="#" 
    //                    class="breadcrumb-item breadcrumb-clickable ${isHome ? 'breadcrumb-home' : ''}"
    //                    data-type="${item.type}"
    //                    data-id="${item.id || ''}"
    //                    data-index="${index}">
    //                     <span class="breadcrumb-icon">${item.icon}</span>
    //                     <span class="breadcrumb-text">${item.name}</span>
    //                 </a>
    //             `;
    //         } else {
    //             // Current item (not clickable)
    //             html += `
    //                 <span class="breadcrumb-item breadcrumb-current">
    //                     <span class="breadcrumb-icon">${item.icon}</span>
    //                     <span class="breadcrumb-text">${item.name}</span>
    //                 </span>
    //             `;
    //         }
    //     });
        
    //     container.innerHTML = html;
    //     this.attachEventHandlers();
    // },
    render() {
       const container = document.querySelector(this.config.containerSelector);
       if (!container) return;
       
       let html = '<div class="breadcrumb-container"><div class="breadcrumb">';
       
       this.currentPath.forEach((item, index) => {
           const isLast = index === this.currentPath.length - 1;
           
           if (item.clickable) {
               const isHome = item.type === 'home';
               html += `
                   <a href="#" 
                      class="breadcrumb-item breadcrumb-clickable ${isHome ? 'breadcrumb-home' : ''}"
                      data-type="${item.type}"
                      data-id="${item.id || ''}"
                      data-index="${index}">
                       <span class="breadcrumb-icon">${item.icon}</span>
                       <span class="breadcrumb-text">${item.name}</span>
                       ${!isLast ? `<span class="breadcrumb-separator">${this.config.separator}</span>` : ''}
                   </a>
               `;
           } else {
               // Current item (not clickable)
               html += `
                   <span class="breadcrumb-item breadcrumb-current">
                       <span class="breadcrumb-icon">${item.icon}</span>
                       <span class="breadcrumb-text">${item.name}</span>
                   </span>
               `;
           }
       });
       
       html += '</div></div>';
       container.innerHTML = html;
       this.attachEventHandlers();
    },
    
    /**
     * Attach click event handlers
     */
    attachEventHandlers() {
        const container = document.querySelector(this.config.containerSelector);
        if (!container) return;
        
        container.querySelectorAll('.breadcrumb-clickable').forEach(item => {
            item.removeEventListener('click', this.handleItemClick);
            item.addEventListener('click', this.handleItemClick.bind(this));
        });
    },
    
    /**
     * Handle breadcrumb item click
     */
    handleItemClick(e) {
        e.preventDefault();
        
        const element = e.currentTarget;
        const type = element.dataset.type;
        const id = element.dataset.id;
        const index = parseInt(element.dataset.index);
        
        // Navigate to home
        if (type === 'home') {
            window.location.href = this.config.homeUrl;
            return;
        }
        
        // Navigate to all items of a type
        if (type === 'level') {
            this.navigateToLevel(this.currentPath[index]);
            return;
        }
        
        // Navigate to specific item
        this.navigateToItem(type, id, index);
    },
    
    /**
     * Navigate to a hierarchy level
     */
    navigateToLevel(levelItem) {
        // Call appropriate function based on level
        if (levelItem.name === 'בתי עלמין' && typeof loadAllCemeteries === 'function') {
            loadAllCemeteries();
        } else if (levelItem.name === 'גושים' && typeof loadAllBlocks === 'function') {
            loadAllBlocks();
        } else if (levelItem.name === 'חלקות' && typeof loadAllPlots === 'function') {
            loadAllPlots();
        } else if (levelItem.name === 'אחוזות קבר' && typeof loadAllAreaGraves === 'function') {
            loadAllAreaGraves();
        } else if (levelItem.name === 'קברים' && typeof loadAllGraves === 'function') {
            loadAllGraves();
        }
    },
    
    /**
     * Navigate to specific item
     */
    navigateToItem(type, id, index) {
        // Trim path to clicked item
        this.currentPath = this.currentPath.slice(0, index + 1);
        
        // Update global selectedItems
        if (window.selectedItems) {
            const order = ['cemetery', 'block', 'plot', 'areaGrave', 'grave'];
            let foundCurrent = false;
            
            for (let itemType of order) {
                if (itemType === type) {
                    foundCurrent = true;
                } else if (foundCurrent) {
                    delete window.selectedItems[itemType];
                }
            }
        }
        
        // Call appropriate navigation function
        const item = this.currentPath[index].data;
        if (item) {
            this.openItem(type, item);
        }
    },
    
    /**
     * Open specific item type
     */
    openItem(type, item) {
        switch(type) {
            case 'cemetery':
                if (typeof openCemetery === 'function') {
                    openCemetery(item.id, item.name);
                }
                break;
            case 'block':
                if (typeof openBlock === 'function') {
                    openBlock(item.id, item.name);
                }
                break;
            case 'plot':
                if (typeof openPlot === 'function') {
                    openPlot(item.id, item.name);
                }
                break;
            case 'areaGrave':
                if (typeof openAreaGrave === 'function') {
                    openAreaGrave(item.id, item.name);
                }
                break;
            case 'grave':
                if (typeof viewGraveDetails === 'function') {
                    viewGraveDetails(item.id);
                }
                break;
        }
    },
    
    /**
     * Bind global event handlers
     */
    bindEventHandlers() {
        // Save path before unload
        window.addEventListener('beforeunload', () => {
            if (window.selectedItems) {
                sessionStorage.setItem('breadcrumbPath', JSON.stringify(window.selectedItems));
            }
        });
    }
};

// Global function for backward compatibility
function updateBreadcrumb(pathOrItems) {
    if (typeof pathOrItems === 'object' && pathOrItems !== null) {
        BreadcrumbManager.update(pathOrItems);
    } else if (!pathOrItems || pathOrItems === 'ראשי') {
        BreadcrumbManager.reset();
    } else {
        // For string paths, try to use selectedItems
        if (window.selectedItems && Object.keys(window.selectedItems).length > 0) {
            BreadcrumbManager.update(window.selectedItems);
        }
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    BreadcrumbManager.init();
    
    // Restore saved path if exists
    const savedPath = sessionStorage.getItem('breadcrumbPath');
    if (savedPath) {
        try {
            const items = JSON.parse(savedPath);
            window.selectedItems = items;
            BreadcrumbManager.update(items);
        } catch (e) {
            console.error('Failed to restore breadcrumb:', e);
        }
    }
});

// Export for global use
window.BreadcrumbManager = BreadcrumbManager;
window.updateBreadcrumb = updateBreadcrumb;