/**
 * UniversalSearch Styles
 * עיצוב מערכת חיפוש אוניברסלית
 */

/* Container */
.universal-search-container {
    margin-bottom: 20px;
    direction: rtl;
}

/* Search Wrapper */
.us-search-wrapper {
    background: #ffffff;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

/* Main Search */
.us-main-search {
    display: flex;
    gap: 12px;
    align-items: center;
}

.us-search-input-wrapper {
    position: relative;
    flex: 1;
}

.us-search-input {
    width: 100%;
    padding: 14px 50px 14px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 16px;
    transition: all 0.2s ease;
    direction: rtl;
}

.us-search-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.us-search-icon {
    position: absolute;
    right: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #9ca3af;
    pointer-events: none;
}

.us-clear-btn {
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    background: #ef4444;
    color: white;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}

.us-clear-btn:hover {
    background: #dc2626;
    transform: translateY(-50%) scale(1.1);
}

/* Advanced Toggle */
.us-advanced-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: #f3f4f6;
    border: 2px solid #e5e7eb;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 500;
    color: #374151;
    cursor: pointer;
    transition: all 0.2s ease;
    white-space: nowrap;
}

.us-advanced-toggle:hover {
    background: #e5e7eb;
    border-color: #d1d5db;
}

.us-advanced-toggle.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.us-advanced-toggle svg {
    width: 18px;
    height: 18px;
}

/* Advanced Panel */
.us-advanced-panel {
    margin-top: 20px;
    padding: 20px;
    background: #f9fafb;
    border-radius: 10px;
    border: 2px solid #e5e7eb;
}

/* Filters Container */
.us-filters-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

/* Filter Group */
.us-filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.us-filter-label {
    font-size: 13px;
    font-weight: 600;
    color: #374151;
}

.us-filter-controls {
    display: flex;
    gap: 8px;
}

.us-filter-input {
    flex: 1;
    padding: 10px 14px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.us-filter-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.us-match-type {
    padding: 10px 12px;
    border: 1.5px solid #d1d5db;
    border-radius: 8px;
    font-size: 13px;
    background: white;
    cursor: pointer;
    transition: all 0.2s ease;
    min-width: 120px;
}

.us-match-type:focus {
    outline: none;
    border-color: #667eea;
}

.us-date-end {
    margin-top: 8px;
}

/* Actions */
.us-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.us-btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
}

.us-btn-primary {
    background: #667eea;
    color: white;
}

.us-btn-primary:hover {
    background: #5568d3;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.us-btn-secondary {
    background: #e5e7eb;
    color: #374151;
}

.us-btn-secondary:hover {
    background: #d1d5db;
}

/* Results Counter */
.us-results-counter {
    margin-top: 16px;
    padding: 12px 16px;
    background: #dbeafe;
    border-radius: 8px;
    border: 1px solid #3b82f6;
}

.us-counter-text {
    font-size: 14px;
    font-weight: 600;
    color: #1e40af;
}

/* Loading State */
.us-loading {
    opacity: 0.5;
    pointer-events: none;
    position: relative;
}

.us-loading::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 40px;
    height: 40px;
    border: 4px solid #f3f4f6;
    border-top-color: #667eea;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}

/* Highlight */
mark {
    background-color: #fef08a;
    color: inherit;
    padding: 2px 4px;
    border-radius: 3px;
}

/* Responsive */
@media (max-width: 768px) {
    .us-main-search {
        flex-direction: column;
    }
    
    .us-filters-container {
        grid-template-columns: 1fr;
    }
    
    .us-filter-controls {
        flex-direction: column;
    }
    
    .us-match-type {
        width: 100%;
    }
    
    .us-actions {
        flex-direction: column;
    }
    
    .us-btn {
        width: 100%;
    }
}

/* Layout Variations */
.us-search-wrapper.vertical .us-main-search {
    flex-direction: column;
}

.us-search-wrapper.compact {
    padding: 12px;
}

.us-search-wrapper.compact .us-main-search {
    gap: 8px;
}

.us-search-wrapper.compact .us-search-input {
    padding: 10px 45px 10px 12px;
    font-size: 14px;
}