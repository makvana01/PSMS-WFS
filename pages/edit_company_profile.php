<?php
$page_title = 'Edit Company Profile';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

if ($role !== 'company') {
    $_SESSION['error_msg'] = "You do not have permission to edit a company profile.";
    redirect('../index.php');
}

$error = '';

// Fetch existing data & profile status
$company_status = getCompanyProfileStatus($pdo, $user_id);
$user = $company_status['user'];
$company = $company_status['company'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name'] ?? ''); // Contact person
    $company_name = sanitize_input($_POST['company_name'] ?? '');
    $industry = sanitize_input($_POST['industry'] ?? '');
    $location = sanitize_input($_POST['location'] ?? '');
    $website = sanitize_input($_POST['website'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    
    // Logo Upload Logic (Max 5MB) - saved in uploads/companies/logos/
    $logo_url = $company['logo_url'] ?? '';
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_logo_exts = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        $allowed_logo_mimes = [
            'image/jpeg',
            'image/pjpeg',
            'image/png',
            'image/webp',
            'image/svg+xml'
        ];
        
        $val_logo = validateUploadedFile($_FILES['logo'], $allowed_logo_exts, $allowed_logo_mimes, 5 * 1024 * 1024);
        if ($val_logo === true) {
            $company_logo_dir = '../uploads/companies/logos';
            if (!is_dir($company_logo_dir)) {
                mkdir($company_logo_dir, 0777, true);
            }
            $ext_logo = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            $new_filename_logo = 'logo_' . $user_id . '_' . uniqid() . '.' . $ext_logo;
            $destination_logo = $company_logo_dir . '/' . $new_filename_logo;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $destination_logo)) {
                // Delete old logo file if exists
                if (!empty($company['logo_url']) && file_exists($company['logo_url'])) {
                    @unlink($company['logo_url']);
                }
                $logo_url = $destination_logo;
            } else {
                $error = "Failed to upload company logo.";
            }
        } else {
            $error = "Company Logo Error: " . $val_logo;
        }
    }

    // Backend validation for ALL required fields
    if (!$error) {
        if (!validateName($name)) {
            $error = "Contact Person Name can only contain alphabetic characters (A-Z, a-z) and spaces (2 to 100 characters).";
        } elseif (empty($company_name)) {
            $error = "Company Name is required.";
        } elseif (empty($industry)) {
            $error = "Industry / Sector is required.";
        } elseif (empty($location)) {
            $error = "Location / City is required.";
        } elseif (empty($website)) {
            $error = "Company Website URL is required.";
        } elseif (empty($description)) {
            $error = "About Company / Description is required.";
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();
            
            // Update users table
            $update_user = "UPDATE users SET name = ? WHERE id = ?";
            $stmt_u = $pdo->prepare($update_user);
            $stmt_u->execute([$name, $user_id]);
            
            // Update companies table
            $update_company = "UPDATE companies SET company_name = ?, industry = ?, location = ?, website = ?, description = ?, logo_url = ? WHERE user_id = ?";
            $stmt_c = $pdo->prepare($update_company);
            $stmt_c->execute([$company_name, $industry, $location, $website, $description, $logo_url, $user_id]);
            
            $pdo->commit();
            
            // Update session name
            $_SESSION['user_name'] = $name;
            
            $_SESSION['success_msg'] = "Company profile updated successfully! Your profile is now 100% complete.";
            redirect('company_profile.php');
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update profile. Please try again.";
        }
    }
}

include '../includes/header.php';
?>

<div class="py-4 fade-in">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="page-header">
                    <h2><i class="fa-solid fa-building me-2"></i>Edit Company Profile</h2>
                </div>
                <a href="company_profile.php" class="btn btn-outline-secondary rounded-pill"><i class="fa-solid fa-arrow-left me-2"></i>Back to Profile</a>
            </div>

            <!-- Profile Completion Banner -->
            <?php renderProfileCompletionBanner($company_status, 'edit_company_profile.php', 'Company Profile', 'All fields marked with * are required to reach 100% profile completeness.'); ?>

            <div class="card card-static mb-4 shadow-sm">
                <div class="card-body p-4">
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-address-card me-2"></i>Contact Details</h5>
                            <span class="small text-muted"><span class="required-mark">*</span> All fields are required</span>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contact Person Name <span class="required-mark">*</span></label>
                                <input type="text" name="name" class="form-control alpha-only-input" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" maxlength="100" required placeholder="Enter contact person name" pattern="[a-zA-Z\s]{2,100}" title="Only letters (A-Z, a-z) and spaces are allowed.">
                                <div class="form-text text-muted small">Only alphabetic letters (A-Z, a-z) and spaces (Max 100 characters).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address <span class="text-muted fw-normal">(Cannot be changed)</span></label>
                                <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                            </div>
                        </div>

                        <hr class="mb-4">
                        <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-building me-2"></i>Company Details</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Company Name <span class="required-mark">*</span></label>
                                <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($company['company_name'] ?? ''); ?>" maxlength="150" required placeholder="e.g. Acme Innovations">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Industry / Sector <span class="required-mark">*</span></label>
                                <input type="text" name="industry" class="form-control" value="<?php echo htmlspecialchars($company['industry'] ?? ''); ?>" maxlength="50" required placeholder="e.g. Information Technology, Finance">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Location / Headquarters <span class="required-mark">*</span></label>
                                <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($company['location'] ?? ''); ?>" maxlength="150" required placeholder="e.g. Surat, Gujarat">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Website URL <span class="required-mark">*</span></label>
                                <input type="url" name="website" class="form-control" value="<?php echo htmlspecialchars($company['website'] ?? ''); ?>" maxlength="150" placeholder="https://example.com" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">About Company <span class="required-mark">*</span></label>
                                <textarea name="description" class="form-control" rows="4" required placeholder="Describe your company, work culture, services, and mission..."><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea>
                            </div>
                        </div>

                        <hr class="mb-4">
                        <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-image me-2"></i>Company Brand Logo</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <?php if(!empty($company['logo_url'])): ?>
                                    <div class="d-flex align-items-center mb-2 gap-3 p-2 border rounded bg-light">
                                        <img src="<?php echo htmlspecialchars($company['logo_url']); ?>" alt="Company Logo" class="rounded" style="max-height: 48px; max-width: 120px; object-fit: contain;">
                                        <a href="<?php echo htmlspecialchars($company['logo_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye me-1"></i>View Current Logo</a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg">
                                <div class="form-text">Allowed formats: JPG, JPEG, PNG, WEBP, SVG. Max size: 5MB. Upload a new file only if you wish to change/update the logo.</div>
                            </div>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 py-2.5 shadow-sm"><i class="fa-solid fa-save me-2"></i>Save & Complete Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
