<?php
// admin/permission_templates.php - MANAGE PERMISSION TEMPLATES
require_once '../config/database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('../auth/login.php');
}

$error = '';
$success = '';
$template_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// ============================================
// HANDLE ACTIONS
// ============================================

// Delete Template
if ($action == 'delete' && $template_id > 0) {
    // Check if it's a system template
    $check_query = "SELECT is_system FROM permission_templates WHERE id = $template_id";
    $check_result = mysqli_query($conn, $check_query);
    $template = mysqli_fetch_assoc($check_result);
    
    if ($template && $template['is_system'] == 1) {
        $error = "Cannot delete system template. System templates are protected.";
    } else {
        $delete_query = "DELETE FROM permission_templates WHERE id = $template_id";
        if (mysqli_query($conn, $delete_query)) {
            $success = "Template deleted successfully!";
        } else {
            $error = "Failed to delete template: " . mysqli_error($conn);
        }
    }
}

// Save Template (Create or Update)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_template'])) {
    $template_name = mysqli_real_escape_string($conn, $_POST['template_name']);
    $template_description = mysqli_real_escape_string($conn, $_POST['template_description']);
    $permissions = isset($_POST['permissions']) ? $_POST['permissions'] : [];
    $is_system = isset($_POST['is_system']) ? 1 : 0;
    
    // Check if system template is being modified
    if ($template_id > 0) {
        $check_query = "SELECT is_system FROM permission_templates WHERE id = $template_id";
        $check_result = mysqli_query($conn, $check_query);
        $existing = mysqli_fetch_assoc($check_result);
        if ($existing && $existing['is_system'] == 1) {
            $is_system = 1; // Keep system flag
        }
    }
    
    if (empty($template_name)) {
        $error = "Template name is required.";
    } else {
        if ($template_id > 0) {
            // Update existing template
            $query = "UPDATE permission_templates 
                      SET template_name = '$template_name', 
                          template_description = '$template_description',
                          is_system = $is_system
                      WHERE id = $template_id";
            if (mysqli_query($conn, $query)) {
                // Clear existing template permissions
                mysqli_query($conn, "DELETE FROM template_permissions WHERE template_id = $template_id");
                
                // Insert new template permissions
                foreach ($permissions as $perm_id => $granted) {
                    $perm_id = (int)$perm_id;
                    $granted = (int)$granted;
                    $insert = "INSERT INTO template_permissions (template_id, permission_id, granted) 
                               VALUES ($template_id, $perm_id, $granted)";
                    mysqli_query($conn, $insert);
                }
                
                $success = "Template updated successfully!";
            } else {
                $error = "Failed to update template: " . mysqli_error($conn);
            }
        } else {
            // Create new template
            $query = "INSERT INTO permission_templates (template_name, template_description, is_system, created_by) 
                      VALUES ('$template_name', '$template_description', $is_system, {$_SESSION['user_id']})";
            if (mysqli_query($conn, $query)) {
                $new_template_id = mysqli_insert_id($conn);
                
                // Insert template permissions
                foreach ($permissions as $perm_id => $granted) {
                    $perm_id = (int)$perm_id;
                    $granted = (int)$granted;
                    $insert = "INSERT INTO template_permissions (template_id, permission_id, granted) 
                               VALUES ($new_template_id, $perm_id, $granted)";
                    mysqli_query($conn, $insert);
                }
                
                $success = "Template created successfully!";
                $template_id = $new_template_id;
            } else {
                $error = "Failed to create template: " . mysqli_error($conn);
            }
        }
    }
}

// Get template data for editing
$edit_template = null;
$template_permissions = [];
if ($template_id > 0 && $action != 'delete') {
    $query = "SELECT * FROM permission_templates WHERE id = $template_id";
    $result = mysqli_query($conn, $query);
    $edit_template = mysqli_fetch_assoc($result);
    if ($edit_template) {
        $template_permissions = getTemplatePermissions($template_id);
    }
}

// Get all permissions for the form
$all_permissions = getAllPermissions();

// Get all templates for the list
$templates_result = mysqli_query($conn, "SELECT * FROM permission_templates ORDER BY template_name");

include '../includes/header.php';
?>

<style>
    .main-content {
        padding: 2rem;
        background: #0a0a0a;
        min-height: 100vh;
    }

    .section-title {
        color: #d4af37;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        border-left: 3px solid #d4af37;
        padding-left: 1rem;
    }

    .templates-layout {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 2rem;
        margin-top: 1rem;
    }

    /* Template List Sidebar */
    .template-list {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
        max-height: 600px;
        overflow-y: auto;
    }

    .template-list h3 {
        color: #d4af37;
        margin-bottom: 1rem;
        font-size: 1rem;
    }

    .template-list .template-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 12px;
        border-radius: 8px;
        margin-bottom: 5px;
        transition: all 0.3s;
        text-decoration: none;
        color: white;
        border-left: 3px solid transparent;
    }

    .template-list .template-item:hover {
        background: rgba(212, 175, 55, 0.1);
    }

    .template-list .template-item.active {
        background: rgba(212, 175, 55, 0.2);
        border-left-color: #d4af37;
    }

    .template-list .template-item .template-name {
        font-weight: 500;
        font-size: 0.9rem;
    }

    .template-list .template-item .template-desc {
        font-size: 0.7rem;
        color: #888;
    }

    .template-list .template-item .system-badge {
        font-size: 0.6rem;
        background: rgba(212, 175, 55, 0.2);
        color: #d4af37;
        padding: 2px 8px;
        border-radius: 20px;
        border: 1px solid #d4af37;
    }

    .template-list .template-item .delete-btn {
        color: #dc3545;
        background: none;
        border: none;
        cursor: pointer;
        font-size: 0.8rem;
        padding: 2px 8px;
        border-radius: 4px;
        transition: all 0.3s;
    }

    .template-list .template-item .delete-btn:hover {
        background: rgba(220, 53, 69, 0.2);
    }

    .template-list .create-btn {
        width: 100%;
        padding: 10px;
        background: rgba(212, 175, 55, 0.2);
        border: 2px dashed rgba(212, 175, 55, 0.4);
        border-radius: 8px;
        color: #d4af37;
        cursor: pointer;
        transition: all 0.3s;
        text-align: center;
        margin-top: 10px;
        text-decoration: none;
        display: block;
    }

    .template-list .create-btn:hover {
        background: rgba(212, 175, 55, 0.3);
    }

    /* Template Editor */
    .template-editor {
        background: #1a1a1a;
        border-radius: 15px;
        padding: 1.5rem;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .template-editor .editor-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .template-editor .editor-header h3 {
        color: #d4af37;
        font-size: 1.1rem;
    }

    .form-group {
        margin-bottom: 1rem;
    }

    .form-group label {
        display: block;
        color: #d4af37;
        margin-bottom: 0.3rem;
        font-size: 0.9rem;
    }

    .form-control {
        width: 100%;
        padding: 10px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 8px;
        color: white;
        font-size: 0.9rem;
    }

    .form-control:focus {
        outline: none;
        border-color: #d4af37;
    }

    textarea.form-control {
        resize: vertical;
        min-height: 60px;
        font-family: inherit;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .checkbox-group {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        max-height: 300px;
        overflow-y: auto;
        padding: 0.5rem;
        background: #111;
        border-radius: 8px;
        border: 1px solid rgba(212, 175, 55, 0.1);
    }

    .checkbox-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.3s;
    }

    .checkbox-item:hover {
        background: rgba(212, 175, 55, 0.05);
    }

    .checkbox-item input[type="checkbox"] {
        appearance: none;
        width: 16px;
        height: 16px;
        background: #2a2a2a;
        border: 1px solid rgba(212, 175, 55, 0.3);
        border-radius: 3px;
        cursor: pointer;
        flex-shrink: 0;
        position: relative;
        transition: all 0.3s;
    }

    .checkbox-item input[type="checkbox"]:checked {
        background: #d4af37;
        border-color: #d4af37;
    }

    .checkbox-item input[type="checkbox"]:checked::after {
        content: "✓";
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        color: #050505;
        font-weight: bold;
        font-size: 12px;
    }

    .checkbox-item .perm-label {
        font-size: 0.8rem;
        color: #ddd;
    }

    .btn-save {
        padding: 10px 30px;
        background: #d4af37;
        color: #050505;
        border: none;
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 1rem;
        width: 100%;
    }

    .btn-save:hover {
        background: #f9e547;
        transform: translateY(-2px);
    }

    .btn-cancel {
        padding: 10px 30px;
        background: #2a2a2a;
        color: #aaa;
        border: 1px solid rgba(212, 175, 55, 0.2);
        border-radius: 25px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
        text-align: center;
    }

    .btn-cancel:hover {
        background: #333;
        color: white;
    }

    .alert {
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 1rem;
    }

    .alert-success {
        background: rgba(40, 167, 69, 0.2);
        border: 1px solid #28a745;
        color: #28a745;
    }

    .alert-danger {
        background: rgba(220, 53, 69, 0.2);
        border: 1px solid #dc3545;
        color: #dc3545;
    }

    .empty-state {
        text-align: center;
        padding: 3rem 0;
        color: #666;
    }

    .empty-state .icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }

    /* Responsive */
    @media (max-width: 1024px) {
        .templates-layout {
            grid-template-columns: 1fr;
        }
        .template-list {
            max-height: 250px;
        }
        .form-row {
            grid-template-columns: 1fr;
        }
        .checkbox-group {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .main-content { padding: 1rem; }
        .section-title { font-size: 1.1rem; }
        .template-editor { padding: 1rem; }
        .template-list { padding: 1rem; }
        .template-editor .editor-header { flex-direction: column; align-items: flex-start; }
    }

    @media (max-width: 480px) {
        .main-content { padding: 0.8rem; }
        .section-title { font-size: 1rem; }
        .checkbox-item .perm-label { font-size: 0.7rem; }
    }
</style>

<div class="main-content">
    <h1 class="section-title">📋 Permission Templates</h1>

    <?php if($error): ?>
        <div class="alert alert-danger">❌ <?php echo $error; ?></div>
    <?php endif; ?>
    <?php if($success): ?>
        <div class="alert alert-success">✅ <?php echo $success; ?></div>
    <?php endif; ?>

    <div class="templates-layout">

        <!-- LEFT: Template List -->
        <div class="template-list">
            <h3>📁 Templates</h3>

            <?php if(mysqli_num_rows($templates_result) == 0): ?>
                <p style="color: #666; text-align: center; padding: 1rem;">No templates found.</p>
            <?php else: ?>
                <?php while($template = mysqli_fetch_assoc($templates_result)): ?>
                    <a href="permission_templates.php?id=<?php echo $template['id']; ?>" class="template-item <?php echo ($template_id == $template['id']) ? 'active' : ''; ?>">
                        <div>
                            <div class="template-name">
                                <?php echo htmlspecialchars($template['template_name']); ?>
                                <?php if($template['is_system']): ?>
                                    <span class="system-badge">⭐ System</span>
                                <?php endif; ?>
                            </div>
                            <div class="template-desc"><?php echo htmlspecialchars($template['template_description']); ?></div>
                        </div>
                        <?php if(!$template['is_system']): ?>
                            <button class="delete-btn" onclick="event.preventDefault(); confirmDelete(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['template_name']); ?>')">✕</button>
                        <?php endif; ?>
                    </a>
                <?php endwhile; ?>
            <?php endif; ?>

            <a href="permission_templates.php?action=create" class="create-btn">➕ Create New Template</a>
        </div>

        <!-- RIGHT: Template Editor -->
        <div class="template-editor">
            <?php if($action == 'create' || $template_id > 0): ?>
                <?php
                $is_edit = ($template_id > 0 && $edit_template);
                $template_name = $is_edit ? $edit_template['template_name'] : '';
                $template_description = $is_edit ? $edit_template['template_description'] : '';
                $is_system = $is_edit ? $edit_template['is_system'] : 0;
                ?>
                <div class="editor-header">
                    <h3><?php echo $is_edit ? '✏️ Edit Template' : '➕ Create New Template'; ?></h3>
                    <a href="permission_templates.php" class="btn-cancel" style="padding: 5px 15px; font-size: 0.8rem;">Cancel</a>
                </div>

                <form method="POST">
                    <input type="hidden" name="template_id" value="<?php echo $template_id; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Template Name</label>
                            <input type="text" name="template_name" class="form-control" value="<?php echo htmlspecialchars($template_name); ?>" required>
                        </div>
                        <div class="form-group" style="display: flex; align-items: center; gap: 1rem;">
                            <label style="margin-bottom: 0;">
                                <input type="checkbox" name="is_system" value="1" <?php echo $is_system ? 'checked' : ''; ?> <?php echo $is_edit && $is_system ? 'disabled' : ''; ?>>
                                System Template
                            </label>
                            <?php if($is_edit && $is_system): ?>
                                <span style="color: #888; font-size: 0.8rem;">(Protected - cannot be deleted)</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="template_description" class="form-control" rows="2"><?php echo htmlspecialchars($template_description); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Select Permissions</label>
                        <div class="checkbox-group">
                            <?php while($perm = mysqli_fetch_assoc($all_permissions)): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="permissions[<?php echo $perm['id']; ?>]" value="1" 
                                        <?php echo isset($template_permissions[$perm['id']]) && $template_permissions[$perm['id']] ? 'checked' : ''; ?>>
                                    <span class="perm-label"><?php echo htmlspecialchars($perm['description']); ?></span>
                                </label>
                            <?php endwhile; ?>
                        </div>
                        <div style="margin-top: 0.5rem;">
                            <button type="button" class="btn-cancel" style="padding: 3px 12px; font-size: 0.7rem;" onclick="selectAllPermissions(true)">Select All</button>
                            <button type="button" class="btn-cancel" style="padding: 3px 12px; font-size: 0.7rem;" onclick="selectAllPermissions(false)">Deselect All</button>
                        </div>
                    </div>

                    <button type="submit" name="save_template" class="btn-save">💾 Save Template</button>
                </form>

                <script>
                    function selectAllPermissions(select) {
                        const checkboxes = document.querySelectorAll('.checkbox-item input[type="checkbox"]');
                        checkboxes.forEach(cb => {
                            cb.checked = select;
                        });
                    }
                </script>

            <?php else: ?>
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <h3>Select or Create a Template</h3>
                    <p>Choose a template from the left to edit, or create a new one.</p>
                    <p style="font-size: 0.8rem; color: #888; margin-top: 0.5rem;">
                        System templates (⭐) are protected and cannot be deleted.
                    </p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); justify-content: center; align-items: center; z-index: 1000;">
    <div class="modal-content" style="background: #1a1a1a; padding: 2rem; border-radius: 15px; max-width: 400px; width: 90%; border: 1px solid rgba(212, 175, 55, 0.3);">
        <h3 style="color: #d4af37; margin-bottom: 1rem;">⚠️ Delete Template</h3>
        <p>Are you sure you want to delete <strong id="deleteTemplateName"></strong>?</p>
        <p style="color: #dc3545; font-size: 0.9rem;">This action cannot be undone.</p>
        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
            <button type="button" class="btn-cancel" onclick="closeDeleteModal()" style="flex: 1; padding: 10px; background: #dc3545; color: white; border: none; border-radius: 5px; cursor: pointer;">Cancel</button>
            <a href="#" id="deleteConfirmBtn" class="btn-confirm" style="flex: 1; padding: 10px; background: #d4af37; color: #050505; border: none; border-radius: 5px; cursor: pointer; font-weight: 600; text-decoration: none; text-align: center;">Delete</a>
        </div>
    </div>
</div>

<script>
    function confirmDelete(templateId, templateName) {
        document.getElementById('deleteTemplateName').textContent = templateName;
        document.getElementById('deleteConfirmBtn').href = 'permission_templates.php?action=delete&id=' + templateId;
        document.getElementById('deleteModal').style.display = 'flex';
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').style.display = 'none';
    }

    // Close modal on outside click
    window.onclick = function(event) {
        if (event.target == document.getElementById('deleteModal')) {
            closeDeleteModal();
        }
    }
</script>

<?php include '../includes/footer.php'; ?>