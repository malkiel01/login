<script>
// הגדר את הפונקציות גלובלית מיד
window.updateGraveSelectors = async function(changedLevel) {
    const cemetery = document.getElementById('cemeterySelect').value;
    const block = document.getElementById('blockSelect').value;
    const plot = document.getElementById('plotSelect').value;
    
    switch(changedLevel) {
        case 'cemetery':
            await loadBlocks(cemetery);
            await loadPlots(cemetery, null);
            clearSelectors(['row', 'area_grave', 'grave']);
            break;
            
        case 'block':
            await loadPlots(cemetery, block);
            clearSelectors(['row', 'area_grave', 'grave']);
            break;
            
        case 'plot':
            if (plot) {
                await loadRows(plot);
                document.getElementById('rowSelect').disabled = false;
            } else {
                clearSelectors(['row', 'area_grave', 'grave']);
                document.getElementById('rowSelect').disabled = true;
            }
            break;
            
        case 'row':
            const row = document.getElementById('rowSelect').value;
            if (row) {
                await loadAreaGraves(row);
                document.getElementById('areaGraveSelect').disabled = false;
            } else {
                clearSelectors(['area_grave', 'grave']);
                document.getElementById('areaGraveSelect').disabled = true;
            }
            break;
            
        case 'area_grave':
            const areaGrave = document.getElementById('areaGraveSelect').value;
            if (areaGrave) {
                await loadGraves(areaGrave);
                document.getElementById('graveSelect').disabled = false;
            } else {
                clearSelectors(['grave']);
                document.getElementById('graveSelect').disabled = true;
            }
            break;
    }
}

// שאר הפונקציות גם צריכות להיות גלובליות
window.loadBlocks = async function(cemeteryId) {
    const blockSelect = document.getElementById('blockSelect');
    if (!blockSelect) return;
    
    let url = `${window.API_BASE || '/dashboard/dashboards/cemeteries/api/'}cemetery-hierarchy.php?action=list&type=block`;
    if (cemeteryId) url += `&parent_id=${cemeteryId}`;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        blockSelect.innerHTML = '<option value="">-- כל הגושים --</option>';
        if (data.success && data.data) {
            data.data.forEach(block => {
                blockSelect.innerHTML += `<option value="${block.id}">${block.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading blocks:', error);
    }
}

window.loadPlots = async function(cemeteryId, blockId) {
    const plotSelect = document.getElementById('plotSelect');
    if (!plotSelect) return;
    
    let url = `${window.API_BASE || '/dashboard/dashboards/cemeteries/api/'}cemetery-hierarchy.php?action=list&type=plot`;
    
    if (blockId) {
        url += `&parent_id=${blockId}`;
    }
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        plotSelect.innerHTML = '<option value="">-- כל החלקות --</option>';
        if (data.success && data.data) {
            data.data.forEach(plot => {
                plotSelect.innerHTML += `<option value="${plot.id}">${plot.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading plots:', error);
    }
}

window.loadRows = async function(plotId) {
    const rowSelect = document.getElementById('rowSelect');
    if (!rowSelect) return;
    
    try {
        const response = await fetch(`${window.API_BASE || '/dashboard/dashboards/cemeteries/api/'}cemetery-hierarchy.php?action=list&type=row&parent_id=${plotId}`);
        const data = await response.json();
        
        rowSelect.innerHTML = '<option value="">-- בחר שורה --</option>';
        if (data.success && data.data) {
            data.data.forEach(row => {
                rowSelect.innerHTML += `<option value="${row.id}">${row.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading rows:', error);
    }
}

window.loadAreaGraves = async function(rowId) {
    const areaGraveSelect = document.getElementById('areaGraveSelect');
    if (!areaGraveSelect) return;
    
    try {
        const response = await fetch(`${window.API_BASE || '/dashboard/dashboards/cemeteries/api/'}cemetery-hierarchy.php?action=list&type=area_grave&parent_id=${rowId}`);
        const data = await response.json();
        
        areaGraveSelect.innerHTML = '<option value="">-- בחר אחוזת קבר --</option>';
        if (data.success && data.data) {
            data.data.forEach(areaGrave => {
                areaGraveSelect.innerHTML += `<option value="${areaGrave.id}">${areaGrave.name}</option>`;
            });
        }
    } catch (error) {
        console.error('Error loading area graves:', error);
    }
}

window.loadGraves = async function(areaGraveId) {
    const graveSelect = document.getElementById('graveSelect');
    if (!graveSelect) return;
    
    try {
        const response = await fetch(`${window.API_BASE || '/dashboard/dashboards/cemeteries/api/'}cemetery-hierarchy.php?action=list&type=grave&parent_id=${areaGraveId}`);
        const data = await response.json();
        
        graveSelect.innerHTML = '<option value="">-- בחר קבר --</option>';
        if (data.success && data.data) {
            data.data.forEach(grave => {
                if (grave.grave_status == 1) {
                    graveSelect.innerHTML += `<option value="${grave.id}">קבר ${grave.grave_number}</option>`;
                }
            });
        }
    } catch (error) {
        console.error('Error loading graves:', error);
    }
}

window.clearSelectors = function(levels) {
    const configs = {
        'row': { id: 'rowSelect', default: '-- בחר חלקה תחילה --', disabled: true },
        'area_grave': { id: 'areaGraveSelect', default: '-- בחר שורה תחילה --', disabled: true },
        'grave': { id: 'graveSelect', default: '-- בחר אחוזת קבר תחילה --', disabled: true }
    };
    
    levels.forEach(level => {
        const config = configs[level];
        if (config) {
            const element = document.getElementById(config.id);
            if (element) {
                element.innerHTML = `<option value="">${config.default}</option>`;
                element.disabled = config.disabled;
            }
        }
    });
}

// טעינה ראשונית - טען גושים וחלקות
setTimeout(() => {
    loadBlocks('');
    loadPlots('', '');
}, 100);
</script>