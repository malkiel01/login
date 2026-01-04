/**
 * Context Menu Handler
 * תפריט לחיצה ימנית למפה
 */

class ContextMenuHandler {
    constructor() {
        this.menu = document.getElementById('contextMenu');
        this.currentTarget = null;
        this.init();
    }

    init() {
        // Prevent default context menu
        document.getElementById('canvasWrapper')?.addEventListener('contextmenu', (e) => {
            e.preventDefault();
        });

        // Close menu on click outside
        document.addEventListener('click', (e) => {
            if (!this.menu.contains(e.target)) {
                this.hide();
            }
        });

        // Close menu on scroll
        document.addEventListener('scroll', () => this.hide(), true);

        // Close menu on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hide();
            }
        });

        // Handle menu item clicks
        this.menu.addEventListener('click', (e) => {
            const item = e.target.closest('li');
            if (item && !item.classList.contains('disabled')) {
                const action = item.dataset.action;
                this.handleAction(action);
            }
        });
    }

    show(x, y, target) {
        this.currentTarget = target;

        // Position menu
        const menuWidth = this.menu.offsetWidth || 180;
        const menuHeight = this.menu.offsetHeight || 200;
        const windowWidth = window.innerWidth;
        const windowHeight = window.innerHeight;

        // Adjust position if menu would go off screen
        let posX = x;
        let posY = y;

        if (x + menuWidth > windowWidth) {
            posX = windowWidth - menuWidth - 10;
        }

        if (y + menuHeight > windowHeight) {
            posY = windowHeight - menuHeight - 10;
        }

        this.menu.style.left = `${posX}px`;
        this.menu.style.top = `${posY}px`;

        // Update menu items based on target
        this.updateMenuItems(target);

        this.menu.classList.add('visible');
    }

    hide() {
        this.menu.classList.remove('visible');
        this.currentTarget = null;
    }

    updateMenuItems(target) {
        const entityData = target?.entityData;

        // Update drill-down item visibility
        const drillDownItem = document.getElementById('menuDrillDown');
        if (drillDownItem && entityData) {
            const hasChildren = window.MAP_CONFIG?.entityConfig[entityData.type]?.children;
            drillDownItem.style.display = hasChildren ? 'flex' : 'none';

            // Update text based on child type
            if (hasChildren) {
                const childTitle = window.MAP_CONFIG?.entityConfig[hasChildren]?.title || 'ילדים';
                drillDownItem.querySelector('span:last-child') ||
                    (drillDownItem.innerHTML = drillDownItem.innerHTML.replace('הצג ילדים', `הצג ${childTitle}`));
            }
        }

        // Show/hide edit-only items based on mode
        const isEditMode = document.body.classList.contains('edit-mode');
        this.menu.querySelectorAll('.edit-only').forEach(item => {
            item.style.display = isEditMode ? 'flex' : 'none';
        });

        // Show/hide divider before edit items
        const editDivider = this.menu.querySelector('.menu-divider.edit-only');
        if (editDivider) {
            editDivider.style.display = isEditMode ? 'block' : 'none';
        }
    }

    handleAction(action) {
        const target = this.currentTarget;
        const entityData = target?.entityData;

        if (!entityData) {
            this.hide();
            return;
        }

        switch (action) {
            case 'viewCard':
                this.viewEntityCard(entityData);
                break;

            case 'zoomTo':
                this.zoomToEntity(entityData);
                break;

            case 'drillDown':
                this.drillDown(entityData);
                break;

            case 'editPolygon':
                this.editPolygon(entityData);
                break;

            case 'deletePolygon':
                this.deletePolygon(entityData, target);
                break;

            case 'setColor':
                this.setPolygonColor(entityData, target);
                break;
        }

        this.hide();
    }

    viewEntityCard(entityData) {
        // Build URL to entity card
        const type = entityData.type;
        const unicId = entityData.unicId;

        // Open in new tab or modal
        const url = `../index.php?view=${type}&id=${unicId}`;
        window.open(url, '_blank');
    }

    zoomToEntity(entityData) {
        if (typeof cemeteryMap !== 'undefined') {
            cemeteryMap.focusOnEntity(entityData.unicId);
        }
    }

    drillDown(entityData) {
        const childType = window.MAP_CONFIG?.entityConfig[entityData.type]?.children;
        if (childType) {
            const mode = window.MAP_CONFIG?.mode || 'view';
            window.location.href = `index.php?type=${entityData.type}&id=${entityData.unicId}&mode=${mode}`;
        }
    }

    editPolygon(entityData) {
        if (typeof polygonEditor !== 'undefined') {
            polygonEditor.editPolygon(entityData.unicId);
        }
    }

    deletePolygon(entityData, target) {
        const confirmMessage = `האם למחוק את הפוליגון של "${entityData.name}"?`;

        if (confirm(confirmMessage)) {
            if (typeof cemeteryMap !== 'undefined') {
                cemeteryMap.canvas.remove(target);
                cemeteryMap.canvas.renderAll();

                // Also update the entity's mapPolygon to null in the data
                const entity = cemeteryMap.entities.find(e => e.unicId === entityData.unicId);
                if (entity) {
                    entity.mapPolygon = null;
                }

                // Update sidebar to show entity as "no-polygon"
                const sidebarItem = document.querySelector(`.sidebar-item[data-id="${entityData.unicId}"]`);
                if (sidebarItem) {
                    sidebarItem.classList.add('no-polygon');
                }
            }
        }
    }

    setPolygonColor(entityData, target) {
        // Show color picker
        const currentColor = target._objects?.[0]?.stroke || '#999999';
        const newColor = prompt('הזן צבע (hex):', currentColor);

        if (newColor && /^#[0-9A-Fa-f]{6}$/.test(newColor)) {
            if (target._objects) {
                const polygon = target._objects.find(o => o.type === 'polygon');
                if (polygon) {
                    polygon.set({
                        stroke: newColor,
                        fill: cemeteryMap.hexToRgba(newColor, 0.2)
                    });
                    cemeteryMap.canvas.renderAll();
                }
            }
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.contextMenuHandler = new ContextMenuHandler();
});
