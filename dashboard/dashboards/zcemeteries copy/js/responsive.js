// responsive.js - טיפול ברספונסיביות

// Toggle Sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('dashboardSidebar');
    const mainContent = document.querySelector('.main-content');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (!sidebar) return;
    
    // Toggle open class
    sidebar.classList.toggle('open');
    
    // Create overlay if not exists
    if (!overlay) {
        const newOverlay = document.createElement('div');
        newOverlay.id = 'sidebarOverlay';
        newOverlay.className = 'sidebar-overlay';
        newOverlay.onclick = closeSidebar;
        document.body.appendChild(newOverlay);
    }
    
    // Toggle overlay
    const overlayElement = document.getElementById('sidebarOverlay');
    if (sidebar.classList.contains('open')) {
        overlayElement.classList.add('active');
        document.body.style.overflow = 'hidden';
    } else {
        overlayElement.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Close Sidebar
function closeSidebar() {
    const sidebar = document.getElementById('dashboardSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (sidebar) {
        sidebar.classList.remove('open');
    }
    
    if (overlay) {
        overlay.classList.remove('active');
    }
    
    document.body.style.overflow = '';
}

// Toggle Entity Item Expansion
function toggleEntityItem(element) {
    element.classList.toggle('expanded');
    
    const expandBtn = element.querySelector('.entity-item-expand');
    if (expandBtn) {
        expandBtn.style.transform = element.classList.contains('expanded') 
            ? 'rotate(90deg)' 
            : 'rotate(0)';
    }
}

// Initialize Entity Items
function initializeEntityItems() {
    const entityItems = document.querySelectorAll('.entity-item');
    
    entityItems.forEach(item => {
        // Add expand button if not exists
        if (!item.querySelector('.entity-item-expand')) {
            const expandBtn = document.createElement('button');
            expandBtn.className = 'entity-item-expand';
            expandBtn.innerHTML = '▶';
            expandBtn.onclick = (e) => {
                e.stopPropagation();
                toggleEntityItem(item);
            };
            
            const header = item.querySelector('.entity-item-header');
            if (header) {
                header.appendChild(expandBtn);
            }
        }
        
        // Make the whole item clickable on mobile
        if (window.innerWidth <= 768) {
            item.onclick = () => toggleEntityItem(item);
        }
    });
}

// Convert Table to Cards on Mobile
function handleTableResponsive() {
    const tables = document.querySelectorAll('.data-table');
    const isMobile = window.innerWidth <= 768;
    
    tables.forEach(table => {
        const wrapper = table.closest('.table-responsive');
        if (!wrapper) return;
        
        // Check if cards already exist
        let cardsContainer = wrapper.parentElement.querySelector('.entity-cards');
        
        if (isMobile) {
            // Hide table, show cards
            wrapper.style.display = 'none';
            
            if (!cardsContainer) {
                cardsContainer = createCardsFromTable(table);
                wrapper.parentElement.appendChild(cardsContainer);
            }
            
            cardsContainer.style.display = 'block';
        } else {
            // Show table, hide cards
            wrapper.style.display = 'block';
            
            if (cardsContainer) {
                cardsContainer.style.display = 'none';
            }
        }
    });
}

// Create Cards from Table Data
function createCardsFromTable(table) {
    const container = document.createElement('div');
    container.className = 'entity-cards';
    
    const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent);
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach((row, index) => {
        const card = document.createElement('div');
        card.className = 'entity-card';
        
        const cells = row.querySelectorAll('td');
        
        // Card Header
        const cardHeader = document.createElement('div');
        cardHeader.className = 'entity-card-header';
        
        const title = document.createElement('div');
        title.className = 'entity-card-title';
        title.textContent = cells[1]?.textContent || `פריט ${index + 1}`;
        
        const badge = document.createElement('span');
        badge.className = 'entity-card-badge badge-info';
        badge.textContent = cells[0]?.textContent || `#${index + 1}`;
        
        cardHeader.appendChild(title);
        cardHeader.appendChild(badge);
        
        // Card Body
        const cardBody = document.createElement('div');
        cardBody.className = 'entity-card-body';
        
        cells.forEach((cell, cellIndex) => {
            if (cellIndex === 0 || cellIndex === 1) return; // Skip ID and Name
            
            const row = document.createElement('div');
            row.className = 'entity-card-row';
            
            const label = document.createElement('span');
            label.className = 'entity-card-label';
            label.textContent = headers[cellIndex] || '';
            
            const value = document.createElement('span');
            value.className = 'entity-card-value';
            value.innerHTML = cell.innerHTML;
            
            row.appendChild(label);
            row.appendChild(value);
            cardBody.appendChild(row);
        });
        
        // Add toggle button
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'entity-card-toggle';
        toggleBtn.innerHTML = '▼';
        toggleBtn.onclick = () => {
            card.classList.toggle('expanded');
            toggleBtn.classList.toggle('expanded');
        };
        
        card.appendChild(toggleBtn);
        card.appendChild(cardHeader);
        card.appendChild(cardBody);
        
        container.appendChild(card);
    });
    
    return container;
}

// Handle Responsive on Resize
let resizeTimer;
function handleResize() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        handleTableResponsive();
        initializeEntityItems();
        
        // Close sidebar on desktop
        if (window.innerWidth > 1024) {
            closeSidebar();
        }
    }, 250);
}

// Initialize on DOM Load
document.addEventListener('DOMContentLoaded', function() {
    // Initialize responsive features
    initializeEntityItems();
    handleTableResponsive();
    
    // Add resize listener
    window.addEventListener('resize', handleResize);
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });
    
    // Update breadcrumb for mobile
    updateBreadcrumbMobile();
});

// Update Breadcrumb for Mobile
function updateBreadcrumbMobile() {
    const breadcrumb = document.querySelector('.breadcrumb');
    if (!breadcrumb) return;
    
    const items = breadcrumb.querySelectorAll('.breadcrumb-item');
    
    if (window.innerWidth <= 480 && items.length > 2) {
        // Show only first and last item on small screens
        items.forEach((item, index) => {
            if (index !== 0 && index !== items.length - 1) {
                item.style.display = 'none';
            }
        });
        
        // Add ellipsis if not exists
        if (!breadcrumb.querySelector('.breadcrumb-ellipsis')) {
            const ellipsis = document.createElement('span');
            ellipsis.className = 'breadcrumb-ellipsis';
            ellipsis.textContent = '...';
            breadcrumb.insertBefore(ellipsis, items[items.length - 1]);
        }
    } else {
        // Show all items on larger screens
        items.forEach(item => {
            item.style.display = '';
        });
        
        // Remove ellipsis
        const ellipsis = breadcrumb.querySelector('.breadcrumb-ellipsis');
        if (ellipsis) {
            ellipsis.remove();
        }
    }
}

// Export functions for global use
window.toggleSidebar = toggleSidebar;
window.closeSidebar = closeSidebar;
window.toggleEntityItem = toggleEntityItem;
window.initializeEntityItems = initializeEntityItems;
window.handleTableResponsive = handleTableResponsive;