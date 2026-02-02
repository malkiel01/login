-- Add new entities to approval system
-- Created: 2026-02-02
-- This migration adds: areaGraves, blocks, plots, graves, cemeteries to the approval system

-- Step 1: Modify ENUM on entity_approval_rules table
ALTER TABLE `entity_approval_rules`
MODIFY COLUMN `entity_type` ENUM('purchases', 'burials', 'customers', 'areaGraves', 'blocks', 'plots', 'graves', 'cemeteries') NOT NULL;

-- Step 2: Modify ENUM on entity_approval_settings table
ALTER TABLE `entity_approval_settings`
MODIFY COLUMN `entity_type` ENUM('purchases', 'burials', 'customers', 'areaGraves', 'blocks', 'plots', 'graves', 'cemeteries') NOT NULL;

-- Step 3: Modify ENUM on pending_entity_operations table
ALTER TABLE `pending_entity_operations`
MODIFY COLUMN `entity_type` ENUM('purchases', 'burials', 'customers', 'areaGraves', 'blocks', 'plots', 'graves', 'cemeteries') NOT NULL;

-- Step 4: Insert default rules for new entities
INSERT IGNORE INTO `entity_approval_rules` (`entity_type`, `action`, `required_approvals`, `require_all_mandatory`, `expires_hours`, `is_active`) VALUES
-- areaGraves (areas)
('areaGraves', 'create', 1, 1, 48, 1),
('areaGraves', 'edit', 1, 1, 48, 1),
('areaGraves', 'delete', 1, 1, 48, 1),
-- blocks
('blocks', 'create', 1, 1, 48, 1),
('blocks', 'edit', 1, 1, 48, 1),
('blocks', 'delete', 1, 1, 48, 1),
-- plots
('plots', 'create', 1, 1, 48, 1),
('plots', 'edit', 1, 1, 48, 1),
('plots', 'delete', 1, 1, 48, 1),
-- graves
('graves', 'create', 1, 1, 48, 1),
('graves', 'edit', 1, 1, 48, 1),
('graves', 'delete', 1, 1, 48, 1),
-- cemeteries
('cemeteries', 'create', 1, 1, 48, 1),
('cemeteries', 'edit', 1, 1, 48, 1),
('cemeteries', 'delete', 1, 1, 48, 1);
