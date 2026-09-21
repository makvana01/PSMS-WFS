<?php
$page_title = 'Edit Profile';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../auth/login.php');
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'];

if ($role !== 'student') {
    $_SESSION['error_msg'] = "You do not have permission to edit a student profile.";
    redirect('../index.php');
}

$error = '';

// Fetch current status
$profile_status = getStudentProfileStatus($pdo, $user_id);
$user = $profile_status['user'];
$student = $profile_status['student'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $phone = sanitize_input($_POST['phone'] ?? '');
    $department = sanitize_input($_POST['department'] ?? '');
    $passing_year = isset($_POST['passing_year']) ? (int)$_POST['passing_year'] : 0;
    $cgpa = isset($_POST['cgpa']) ? (float)$_POST['cgpa'] : 0.0;
    $skills = sanitize_input($_POST['skills'] ?? '');
    $bio = sanitize_input($_POST['bio'] ?? '');
    
    // File upload logic for resume (Max 5MB) - saved in uploads/students/resumes/
    $resume_url = $student['resume_url'] ?? ''; // keep old by default
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_exts = ['pdf', 'doc', 'docx'];
        $allowed_mimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/octet-stream'
        ];
        
        $val_result = validateUploadedFile($_FILES['resume'], $allowed_exts, $allowed_mimes, 5 * 1024 * 1024);
        if ($val_result === true) {
            // Ensure student resumes directory exists
            $student_resume_dir = '../uploads/students/resumes';
            if (!is_dir($student_resume_dir)) {
                mkdir($student_resume_dir, 0777, true);
            }
            $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
            $new_filename = 'resume_' . $user_id . '_' . uniqid() . '.' . $ext;
            $destination = $student_resume_dir . '/' . $new_filename;
            
            if (move_uploaded_file($_FILES['resume']['tmp_name'], $destination)) {
                // Remove old resume file if exists
                if (!empty($student['resume_url']) && file_exists($student['resume_url'])) {
                    @unlink($student['resume_url']);
                }
                $resume_url = $destination;
            } else {
                $error = "Failed to upload resume document.";
            }
        } else {
            $error = "Resume Error: " . $val_result;
        }
    }

    // File upload logic for student ID card (I-Card) (Max 5MB) - saved in uploads/students/id_cards/
    $id_card_url = $student['id_card_url'] ?? ''; // keep old by default
    if (!$error && isset($_FILES['id_card']) && $_FILES['id_card']['error'] !== UPLOAD_ERR_NO_FILE) {
        $allowed_id_exts = ['pdf', 'jpg', 'jpeg', 'png'];
        $allowed_id_mimes = [
            'application/pdf',
            'image/jpeg',
            'image/pjpeg',
            'image/png'
        ];
        
        $val_id_result = validateUploadedFile($_FILES['id_card'], $allowed_id_exts, $allowed_id_mimes, 5 * 1024 * 1024);
        if ($val_id_result === true) {
            // Ensure student ID cards directory exists
            $student_id_dir = '../uploads/students/id_cards';
            if (!is_dir($student_id_dir)) {
                mkdir($student_id_dir, 0777, true);
            }
            $ext_id = strtolower(pathinfo($_FILES['id_card']['name'], PATHINFO_EXTENSION));
            $new_filename_id = 'id_card_' . $user_id . '_' . uniqid() . '.' . $ext_id;
            $destination_id = $student_id_dir . '/' . $new_filename_id;
            
            if (move_uploaded_file($_FILES['id_card']['tmp_name'], $destination_id)) {
                // Remove old ID card file if exists
                if (!empty($student['id_card_url']) && file_exists($student['id_card_url'])) {
                    @unlink($student['id_card_url']);
                }
                $id_card_url = $destination_id;
            } else {
                $error = "Failed to upload Student ID Card.";
            }
        } else {
            $error = "ID Card Error: " . $val_id_result;
        }
    }

    // Backend validation for ALL required fields
    if (!$error) {
        if (!validateName($name)) {
            $error = "Full Name can only contain alphabetic characters (A-Z, a-z) and spaces (2 to 100 characters).";
        } elseif (!($clean_phone = validateIndianPhone($phone))) {
            $error = "Please enter a valid 10-digit Indian mobile number starting with 6, 7, 8, or 9.";
        } else {
            $phone = $clean_phone; // Save normalized 10-digit number
            if (empty($department)) {
                $error = "Course / Department is required.";
            } elseif ($passing_year < 1990 || $passing_year > 2100) {
                $error = "Please enter a valid Passing Year (e.g. 2025).";
            } elseif ($cgpa <= 0 || $cgpa > 10) {
                $error = "Please enter a valid CGPA between 0.01 and 10.00.";
            } elseif (empty($skills)) {
                $error = "Skills field is required. Please list at least one skill.";
            } elseif (empty($bio)) {
                $error = "Bio / Career Objective is required.";
            } elseif (empty($resume_url)) {
                $error = "Resume document is required. Please upload your resume.";
            } elseif (empty($id_card_url)) {
                $error = "Student ID Card (I-Card) is required. Please upload your student ID card.";
            }
        }
    }

    if (!$error) {
        try {
            $pdo->beginTransaction();
            
            // Update users table
            $update_user = "UPDATE users SET name = ? WHERE id = ?";
            $stmt_u = $pdo->prepare($update_user);
            $stmt_u->execute([$name, $user_id]);
            
            // Update students table
            $update_student = "UPDATE students SET phone = ?, department = ?, passing_year = ?, cgpa = ?, skills = ?, bio = ?, resume_url = ?, id_card_url = ? WHERE user_id = ?";
            $stmt_s = $pdo->prepare($update_student);
            $stmt_s->execute([$phone, $department, $passing_year, $cgpa, $skills, $bio, $resume_url, $id_card_url, $user_id]);
            
            $pdo->commit();
            
            // Update session name
            $_SESSION['user_name'] = $name;
            
            $_SESSION['success_msg'] = "Profile updated successfully! Your profile is now 100% complete.";
            redirect('profile.php');
            
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
                    <h2><i class="fa-solid fa-user-pen me-2"></i>Edit Profile</h2>
                </div>
                <a href="profile.php" class="btn btn-outline-secondary rounded-pill"><i class="fa-solid fa-arrow-left me-2"></i>Back to Profile</a>
            </div>

            <!-- Profile Completion Banner -->
            <?php renderProfileCompletionBanner($profile_status, 'edit_profile.php', 'Student Profile', 'All fields marked with * are required to reach 100% profile completeness.'); ?>

            <div class="card card-static mb-4 shadow-sm">
                <div class="card-body p-4">
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($_SESSION['error_msg'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fa-solid fa-circle-exclamation me-2"></i><?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0 text-primary"><i class="fa-solid fa-id-card me-2"></i>Basic Information</h5>
                            <span class="small text-muted"><span class="required-mark">*</span> All fields are required</span>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name <span class="required-mark">*</span></label>
                                <input type="text" name="name" class="form-control alpha-only-input" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" maxlength="100" required placeholder="Enter full name" pattern="[a-zA-Z\s]{2,100}" title="Only letters (A-Z, a-z) and spaces are allowed.">
                                <div class="form-text text-muted small">Only alphabetic letters (A-Z, a-z) and spaces (Max 100 characters).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address <span class="text-muted fw-normal">(Cannot be changed)</span></label>
                                <input type="email" class="form-control bg-light" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone Number <span class="required-mark">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-phone"></i></span>
                                    <input type="tel" name="phone" class="form-control phone-input" value="<?php echo htmlspecialchars($student['phone'] ?? ''); ?>" required placeholder="10-digit mobile number (e.g. 9876543210)" pattern="[6-9][0-9]{9}" maxlength="15" title="Please enter a valid 10-digit Indian phone number starting with 6, 7, 8, or 9.">
                                </div>
                                <div class="form-text text-muted small">10-digit Indian mobile number starting with 6, 7, 8, or 9.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Course / Department <span class="required-mark">*</span></label>
                                <input type="text" name="department" class="form-control" value="<?php echo htmlspecialchars($student['department'] ?? ''); ?>" maxlength="100" required placeholder="e.g. BCA, MCA, B.Sc IT">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Passing Year <span class="required-mark">*</span></label>
                                <input type="number" name="passing_year" class="form-control" value="<?php echo !empty($student['passing_year']) ? htmlspecialchars($student['passing_year']) : ''; ?>" min="2000" max="2100" required placeholder="e.g. 2026">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">CGPA / Percentage <span class="required-mark">*</span></label>
                                <input type="number" step="0.01" name="cgpa" class="form-control" value="<?php echo (!empty($student['cgpa']) && (float)$student['cgpa'] > 0) ? htmlspecialchars($student['cgpa']) : ''; ?>" min="0.01" max="10" required placeholder="e.g. 8.50">
                            </div>
                        </div>

                        <hr class="mb-4">
                        <h5 class="fw-bold mb-3 text-primary"><i class="fa-solid fa-file-lines me-2"></i>Additional Details</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Skills (Comma separated) <span class="required-mark">*</span></label>
                                <input type="text" name="skills" class="form-control" value="<?php echo htmlspecialchars($student['skills'] ?? ''); ?>" placeholder="e.g. PHP, JavaScript, MySQL, Python, React" required>
                                <div class="form-text">Enter technical & soft skills separated by commas.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Bio / Career Objective <span class="required-mark">*</span></label>
                                <textarea name="bio" class="form-control" rows="4" required placeholder="Write a short summary about your background, career goals, and strengths..."><?php echo htmlspecialchars($student['bio'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Resume Upload <span class="required-mark">*</span></label>
                                <?php if(!empty($student['resume_url'])): ?>
                                    <div class="mb-2 p-2 bg-light rounded d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fa-solid fa-circle-check text-success me-2"></i>
                                            <span class="fw-semibold small">Current Resume on file</span>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($student['resume_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-download me-1"></i>View Current Resume</a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="resume" class="form-control" accept=".pdf,.doc,.docx" <?php echo empty($student['resume_url']) ? 'required' : ''; ?>>
                                <div class="form-text">Allowed formats: PDF, DOC, DOCX. Max size: 5MB. <?php echo !empty($student['resume_url']) ? 'Upload a new file only if you wish to replace the existing one.' : '<span class="text-danger fw-semibold">Resume is required to complete your profile.</span>'; ?></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Student ID Card (I-Card) Upload <span class="required-mark">*</span></label>
                                <?php if(!empty($student['id_card_url'])): ?>
                                    <div class="mb-2 p-2 bg-light rounded d-flex align-items-center justify-content-between">
                                        <div>
                                            <i class="fa-solid fa-circle-check text-success me-2"></i>
                                            <span class="fw-semibold small">Current Student ID Card on file</span>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($student['id_card_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-id-card me-1"></i>View Current ID Card</a>
                                    </div>
                                <?php endif; ?>
                                <input type="file" name="id_card" class="form-control" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($student['id_card_url']) ? 'required' : ''; ?>>
                                <div class="form-text">Allowed formats: JPG, JPEG, PNG, PDF. Max size: 5MB. <?php echo !empty($student['id_card_url']) ? 'Upload a new file only if you wish to replace the existing one.' : '<span class="text-danger fw-semibold">Student ID Card (I-Card) is mandatory to verify and complete your profile.</span>'; ?></div>
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
