/**
 * Main JavaScript Entry Point
 *
 * נקודת כניסה ראשית למערכת JavaScript
 * מייבא ומאתחל את כל המודולים
 *
 * @package PDFEditor
 * @since Phase 4 Refactoring
 *
 * Phase 4.0: Infrastructure setup
 * - Created modular JS structure
 * - Prepared for ES6 modules
 * - Legacy code kept in legacy.js for now
 *
 * TODO Phase 4.1+: Break down legacy.js into modules:
 * - core/config.js - Global constants and configuration
 * - core/state.js - State management
 * - modules/pdf-viewer.js - PDF rendering and zoom
 * - modules/text-editor.js - Text editing functionality
 * - modules/image-handler.js - Image handling
 * - modules/upload-handler.js - File upload
 * - modules/process-handler.js - PDF processing
 * - modules/template-manager.js - Template management
 * - utils/drag-drop.js - Drag and drop utilities
 * - utils/fonts.js - Font loading
 */

// For now, we import the entire legacy script
// This will be broken down into modules in future phases
import './legacy.js';

console.log('PDF Editor initialized - Phase 4.0');
