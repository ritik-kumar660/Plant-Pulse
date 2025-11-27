<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['identified_plant'])) {
    header('Location: index.php');
    exit;
}

$plant = $_SESSION['identified_plant'];
$uploaded_image = $_SESSION['plant_image']; // Path to the image user uploaded
$api_image = $plant['api_image'] ?? $uploaded_image; // Use API image if available, else fallback

// Convert probability to percentage for display
$confidence_percent = round(($plant['confidence'] ?? 0) * 100);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plant Details - <?php echo htmlspecialchars($plant['name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container mt-4">
        <div class="row">
            <div class="col-md-6">
                <div class="plant-image-container mb-3">
                    <img src="<?php echo htmlspecialchars($api_image); ?>" alt="<?php echo htmlspecialchars($plant['name']); ?>" class="img-fluid rounded">
                    <small class="d-block text-center mt-1">Image source: <?php echo ($api_image === $uploaded_image) ? 'Your Upload' : 'API Suggestion'; ?></small>
                </div>
                 <?php if ($api_image !== $uploaded_image): ?>
                    <div class="uploaded-image-container mb-3">
                        <small>Your uploaded image:</small>
                        <img src="<?php echo htmlspecialchars($uploaded_image); ?>" alt="Uploaded Plant" class="img-fluid rounded" style="max-height: 150px;">
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <div class="plant-details">
                    <h1><?php echo htmlspecialchars($plant['name']); ?></h1>
                    
                    <?php if (!empty($plant['scientific_name'])): ?>
                        <p class="scientific-name fst-italic"><?php echo htmlspecialchars($plant['scientific_name']); ?></p>
                    <?php endif; ?>

                    <?php if (!empty($plant['common_names'])) : ?>
                        <p><strong>Common Names:</strong> <?php echo htmlspecialchars(implode(', ', $plant['common_names'])); ?></p>
                    <?php endif; ?>
                    
                    <div class="confidence-badge mb-3">
                        <span class="badge <?php echo ($confidence_percent >= 70) ? 'bg-success' : (($confidence_percent >= 40) ? 'bg-warning text-dark' : 'bg-danger'); ?>">
                            <?php echo $confidence_percent; ?>% Confidence
                        </span>
                    </div>

                    <?php if (!empty($plant['wiki_description'])) : ?>
                        <div class="description mt-3">
                            <h3>Description</h3>
                            <p><?php echo nl2br(htmlspecialchars($plant['wiki_description'])); ?></p>
                            <?php if (!empty($plant['api_url'])): ?>
                                <a href="<?php echo htmlspecialchars($plant['api_url']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">More Info (plant.id)</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">Care Instructions</h3>
                        <p class="text-gray-600 whitespace-pre-line"><?php echo htmlspecialchars($plant['care_instructions']); ?></p>
                    </div>

                    <div class="mb-6">
                        <h3 class="text-xl font-bold text-gray-800 mb-2">Common Pests and Diseases</h3>
                        <p class="text-gray-600 whitespace-pre-line">
                            <?php 
                            if (isset($plant['pest_information']) && !empty($plant['pest_information'])) {
                                echo htmlspecialchars($plant['pest_information']);
                            } else {
                                echo "No specific pest information available for this plant. Please research common pests and diseases for " . 
                                     htmlspecialchars($plant['name']);
                            }
                            ?>
                        </p>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="save-plant mt-4">
                            <form action="save_plant.php" method="POST">
                                <input type="hidden" name="plant_name" value="<?php echo htmlspecialchars($plant['name']); ?>">
                                <input type="hidden" name="scientific_name" value="<?php echo htmlspecialchars($plant['scientific_name'] ?? ''); ?>">
                                <input type="hidden" name="care_instructions" value="<?php echo htmlspecialchars($plant['care_instructions']); ?>">
                                <!-- Use the API image if available, otherwise the uploaded one -->
                                <input type="hidden" name="image_path" value="<?php echo htmlspecialchars($api_image); ?>">
                                <button type="submit" class="btn btn-primary">Save to My Plants</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mt-4">
                            <p>Want to save this plant and get care reminders? <a href="register.php">Register</a> or <a href="login.php">Login</a> to your account.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="related-pests">
                    <!-- Removed placeholder text about common pests -->
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>
</html> 