/**
 * ContextMenu - ъфшйи чмйч йорй
 * Version: 1.0.0
 *
 * озмчд мрйдем ъфшйи дчщш (context menu) бофд
 * Usage:
 *   const contextMenu = new ContextMenu({
 *     menuId: 'mapContextMenu',
 *     onAction: (actionName, data) => {...}
 *   });
 *   contextMenu.showForCanvas(x, y, isInsideBoundary, canvasPos);
 *   contextMenu.showForObject(x, y, targetObject);
 */

export class ContextMenu {
    constructor(options = {}) {
        this.options = {
            menuId: options.menuId || 'mapContextMenu',
            contentId: options.contentId || 'contextMenuContent',
            onAction: options.onAction || null,
            checkBoundary: options.checkBoundary || null  // Function to check if has boundary
        };

        this.menu = null;
        this.content = null;
        this.currentTarget = null;
        this.currentPosition = { x: 0, y: 0 };
    }

    /**
     * аъзем едъзбшеъ м-DOM
     */
    init() {
        this.menu = document.getElementById(this.options.menuId);
        this.content = document.getElementById(this.options.contentId);

        if (!this.menu || !this.content) {
            console.warn('ContextMenu: menu or content element not found');
            return false;
        }

        console.log(' ContextMenu initialized');
        return true;
    }

    /**
     * дцвъ ъфшйи лммй (canvas) - мдесфъ ъоерд, ичси, цешеъ
     * @param {number} clientX - Mouse X position
     * @param {number} clientY - Mouse Y position
     * @param {boolean} isInsideBoundary - дан дчмйч дйд бъек двбем
     * @param {object} canvasPosition - {x, y} ойчен тм дчрбс
     */
    showForCanvas(clientX, clientY, isInsideBoundary, canvasPosition) {
        if (!this.menu || !this.content) return;

        // щоеш ойчен мщйоещ бфтемеъ
        this.currentPosition = canvasPosition || { x: clientX, y: clientY };
        this.currentTarget = null;

        // бгеч ан йщ вбем
        const hasBoundary = this.options.checkBoundary ? this.options.checkBoundary() : true;

        if (!hasBoundary) {
            // айп вбем - дцв дегтд
            this.content.innerHTML = `
                <div class="context-menu-item disabled">
                    <span class="context-menu-icon"> </span>
                    <span>йщ мдвгйш вбем офд ъзймд</span>
                </div>
            `;
        } else if (isInsideBoundary) {
            // ъфшйи швйм - бъек двбем
            this.content.innerHTML = `
                <div class="context-menu-item" data-action="addImage">
                    <span class="context-menu-icon">=М</span>
                    <span>десу ъоерд / PDF</span>
                </div>
                <div class="context-menu-item" data-action="addText">
                    <span class="context-menu-icon">=н</span>
                    <span>десу ичси</span>
                </div>
                <div class="context-menu-separator"></div>
                <div class="context-menu-item" data-action="addRect">
                    <span class="context-menu-icon"></span>
                    <span>десу омбп</span>
                </div>
                <div class="context-menu-item" data-action="addCircle">
                    <span class="context-menu-icon">U</span>
                    <span>десу тйвем</span>
                </div>
                <div class="context-menu-item" data-action="addLine">
                    <span class="context-menu-icon">=Я</span>
                    <span>десу че</span>
                </div>
            `;

            // Attach event listeners
            this.attachActionListeners();
        } else {
            // озех мвбем
            this.content.innerHTML = `
                <div class="context-menu-item disabled">
                    <span class="context-menu-icon no-entry-icon">=Ћ</span>
                    <span>ма рйъп мдесйу озех мвбем</span>
                </div>
            `;
        }

        // дцв бтогд рлерд
        this.position(clientX, clientY);
    }

    /**
     * дцвъ ъфшйи маебййчи (тн афщшеъ озйчд, дбад мзжйъ, щмйзд мазеш)
     * @param {number} clientX - Mouse X position
     * @param {number} clientY - Mouse Y position
     * @param {object} targetObject - Fabric object
     */
    showForObject(clientX, clientY, targetObject) {
        if (!this.menu || !this.content) return;

        // щоеш аъ даебййчи
        this.currentTarget = targetObject;

        // ъфшйи тн афщшейеъ аебййчи
        this.content.innerHTML = `
            <div class="context-menu-item" data-action="deleteObject">
                <span class="context-menu-icon">=б</span>
                <span>озч фшйи</span>
            </div>
            <div class="context-menu-separator"></div>
            <div class="context-menu-item" data-action="bringToFront">
                <span class="context-menu-icon"></span>
                <span>дба мзжйъ</span>
            </div>
            <div class="context-menu-item" data-action="sendToBack">
                <span class="context-menu-icon"></span>
                <span>щмз мшчт</span>
            </div>
        `;

        // Attach event listeners
        this.attachActionListeners();

        // дцв бтогд рлерд
        this.position(clientX, clientY);
    }

    /**
     * ойчен дъфшйи бтогд рлерд (морйтъ йцйад одоск)
     * @param {number} clientX
     * @param {number} clientY
     * @private
     */
    position(clientX, clientY) {
        // ойчен дъфшйи
        this.menu.style.position = 'fixed';
        this.menu.style.left = clientX + 'px';
        this.menu.style.top = clientY + 'px';
        this.menu.style.display = 'block';

        // бгйчд ан дъфшйи йеца одоск
        const menuRect = this.menu.getBoundingClientRect();

        // йцйад ойойп
        if (menuRect.right > window.innerWidth) {
            this.menu.style.left = (clientX - menuRect.width) + 'px';
        }

        // йцйад омоид
        if (menuRect.bottom > window.innerHeight) {
            this.menu.style.top = (clientY - menuRect.height) + 'px';
        }
    }

    /**
     * десфъ event listeners мфшйийн
     * @private
     */
    attachActionListeners() {
        const items = this.content.querySelectorAll('.context-menu-item:not(.disabled)');

        items.forEach(item => {
            item.addEventListener('click', () => {
                const action = item.getAttribute('data-action');

                if (action && this.options.onAction) {
                    this.options.onAction(action, {
                        target: this.currentTarget,
                        position: this.currentPosition
                    });
                }

                this.hide();
            });
        });
    }

    /**
     * дсъшъ дъфшйи
     */
    hide() {
        if (this.menu) {
            this.menu.style.display = 'none';
        }
        this.currentTarget = null;
    }

    /**
     * чбмъ ойчен релзй
     */
    getPosition() {
        return this.currentPosition;
    }

    /**
     * чбмъ аебййчи релзй
     */
    getTarget() {
        return this.currentTarget;
    }

    /**
     * Debug info
     */
    debug() {
        console.group('=Ы ContextMenu');
        console.log('Menu:', this.menu ? 'found' : 'not found');
        console.log('Content:', this.content ? 'found' : 'not found');
        console.log('Current Target:', this.currentTarget);
        console.log('Current Position:', this.currentPosition);
        console.groupEnd();
    }
}
