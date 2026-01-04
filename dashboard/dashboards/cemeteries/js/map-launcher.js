/**
 * Map Launcher - מנהל פתיחת המפה
 * Version: 3.0.0 - Modular Architecture
 *
 * Simple launcher that opens the map interface in map/index.php
 */

// יצירת המודל בטעינה
document.addEventListener('DOMContentLoaded', function() {
    createMapLauncherModal();
});

/**
 * יצירת מודל בחירת ישות למפה
 */
function createMapLauncherModal() {
    if (document.getElementById('mapLauncherModal')) return;

    const modalHTML = `
        <div id="mapLauncherModal" class="map-launcher-overlay" style="display: none;">
            <div class="map-launcher-modal">
                <div class="map-launcher-header">
                    <h3>פתיחת מפת בית עלמין</h3>
                    <button type="button" class="map-launcher-close" onclick="closeMapLauncher()">&times;</button>
                </div>
                <div class="map-launcher-body">
                    <div class="map-launcher-field">
                        <label for="mapEntityType">סוג ישות:</label>
                        <select id="mapEntityType" class="map-launcher-select" onchange="loadEntitiesForType()">
                            <option value="">-- בחר סוג ישות --</option>
                            <option value="cemetery">בית עלמין</option>
                            <option value="block">גוש</option>
                            <option value="plot">חלקה</option>
                            <option value="areaGrave">אחוזת קבר</option>
                        </select>
                    </div>
                    <div class="map-launcher-field">
                        <label for="mapEntitySelect">בחר ישות:</label>
                        <select id="mapEntitySelect" class="map-launcher-select" disabled>
                            <option value="">-- תחילה בחר סוג ישות --</option>
                        </select>
                    </div>
                    <div id="entityLoadingIndicator" class="map-launcher-loading" style="display: none;">
                        טוען ישויות...
                    </div>
                </div>
                <div class="map-launcher-footer">
                    <button type="button" class="btn-secondary" onclick="closeMapLauncher()">ביטול</button>
                    <button type="button" class="btn-primary" onclick="launchMap()">פתח מפה</button>
                </div>
            </div>
        </div>
    `;

    // הוספת סגנונות
    const styles = document.createElement('style');
    styles.id = 'mapLauncherStyles';
    styles.textContent = `
        .map-launcher-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        .map-launcher-modal {
            background: white;
            border-radius: 12px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            direction: rtl;
        }
        .map-launcher-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        .map-launcher-header h3 {
            margin: 0;
            font-size: 18px;
            color: #1f2937;
        }
        .map-launcher-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #6b7280;
        }
        .map-launcher-close:hover {
            color: #374151;
        }
        .map-launcher-body {
            padding: 20px;
        }
        .map-launcher-field {
            margin-bottom: 16px;
        }
        .map-launcher-field label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #374151;
        }
        .map-launcher-select,
        .map-launcher-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
            direction: rtl;
        }
        .map-launcher-select:disabled {
            background: #f3f4f6;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .map-launcher-loading {
            text-align: center;
            color: #6b7280;
            font-size: 14px;
            padding: 10px;
            font-style: italic;
        }
        .map-launcher-footer {
            display: flex;
            justify-content: flex-start;
            gap: 10px;
            padding: 16px 20px;
            border-top: 1px solid #e5e7eb;
            background: #f9fafb;
            border-radius: 0 0 12px 12px;
        }
        .map-launcher-footer .btn-primary {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
        }
        .map-launcher-footer .btn-primary:hover {
            background: #2563eb;
        }
        .map-launcher-footer .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .map-launcher-footer .btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }
        .map-launcher-footer .btn-secondary:hover {
            background: #f9fafb;
        }
    `;

    document.head.appendChild(styles);
    document.body.insertAdjacentHTML('beforeend', modalHTML);
}

/**
 * פתיחת מודל הלאנצ'ר
 */
function openMapLauncher() {
    const modal = document.getElementById('mapLauncherModal');
    if (modal) {
        modal.style.display = 'flex';
        document.getElementById('mapEntityType').focus();
    }
}

/**
 * סגירת מודל הלאנצ'ר
 */
function closeMapLauncher() {
    const modal = document.getElementById('mapLauncherModal');
    if (modal) {
        modal.style.display = 'none';
        document.getElementById('mapEntityType').value = '';
        document.getElementById('mapEntitySelect').value = '';
        document.getElementById('mapEntitySelect').disabled = true;
        document.getElementById('mapEntitySelect').innerHTML = '<option value="">-- תחילה בחר סוג ישות --</option>';
    }
}

/**
 * טעינת רשימת ישויות לפי הסוג שנבחר
 */
async function loadEntitiesForType() {
    const entityType = document.getElementById('mapEntityType').value;
    const entitySelect = document.getElementById('mapEntitySelect');
    const loadingIndicator = document.getElementById('entityLoadingIndicator');

    // איפוס ה-select
    entitySelect.innerHTML = '<option value="">-- בחר ישות --</option>';
    entitySelect.disabled = true;

    if (!entityType) {
        entitySelect.innerHTML = '<option value="">-- תחילה בחר סוג ישות --</option>';
        return;
    }

    // הצגת אינדיקטור טעינה
    loadingIndicator.style.display = 'block';

    try {
        // קריאה ל-API לקבלת רשימת הישויות
        const response = await fetch(`api/map-api.php?action=listEntities&type=${entityType}`);
        const data = await response.json();

        if (data.success && data.entities) {
            // מילוי ה-select עם הישויות
            data.entities.forEach(entity => {
                const option = document.createElement('option');
                option.value = entity.unicId;
                option.textContent = entity.name || entity.unicId;
                entitySelect.appendChild(option);
            });

            entitySelect.disabled = false;

            if (data.entities.length === 0) {
                entitySelect.innerHTML = '<option value="">-- לא נמצאו ישויות --</option>';
            }
        } else {
            throw new Error(data.error || 'שגיאה בטעינת הישויות');
        }
    } catch (error) {
        console.error('Error loading entities:', error);
        entitySelect.innerHTML = '<option value="">-- שגיאה בטעינת הנתונים --</option>';
        alert(`שגיאה בטעינת רשימת הישויות:\n${error.message}`);
    } finally {
        loadingIndicator.style.display = 'none';
    }
}

/**
 * פתיחת המפה
 */
async function launchMap() {
    const entityType = document.getElementById('mapEntityType').value;
    const unicId = document.getElementById('mapEntitySelect').value;

    if (!entityType) {
        alert('נא לבחור סוג ישות');
        document.getElementById('mapEntityType').focus();
        return;
    }

    if (!unicId) {
        alert('נא לבחור ישות מהרשימה');
        document.getElementById('mapEntitySelect').focus();
        return;
    }

    const entityNames = {
        cemetery: 'בית עלמין',
        block: 'גוש',
        plot: 'חלקה',
        areaGrave: 'אחוזת קבר'
    };

    try {
        // סגירת המודל
        closeMapLauncher();

        // פתיחת המפה בדף ייעודי
        const mapUrl = `map/index.php?type=${entityType}&id=${unicId}&mode=edit`;
        window.location.href = mapUrl;

    } catch (error) {
        console.error('Error launching map:', error);
        alert(`שגיאה בפתיחת המפה:\n${error.message}`);
    }
}
