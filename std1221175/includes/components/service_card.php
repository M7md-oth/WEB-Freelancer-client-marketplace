<?php

function renderServiceCard($service, $options = []) {
    $showFeaturedBadge = $options['showFeaturedBadge'] ?? ($service["featured_status"] === "Yes");
    $pricePrefix = $options['pricePrefix'] ?? '';
    
    $serviceUrl = url("services/details.php?id=" . $service["service_id"]);
    
    $profilePhoto = !empty($service["profile_photo"]) 
        ? BASE_URL . "/" . htmlspecialchars($service["profile_photo"]) 
        : DEFAULT_PROFILE_IMAGE;
    
    $serviceImage = !empty($service["image_1"]) 
        ? BASE_URL . "/" . htmlspecialchars($service["image_1"]) 
        : DEFAULT_SERVICE_IMAGE;
    ?>
    <div class="service-card">
        <?php if ($showFeaturedBadge): ?>
            <span class="service-card-featured-badge">Featured</span>
        <?php endif; ?>
        
        <a href="<?= $serviceUrl ?>">
            <img src="<?= $serviceImage ?>" 
                 alt="<?= htmlspecialchars($service["title"]) ?>" 
                 class="service-card-image">
        </a>
        
        <div class="service-card-content">
            <h3 class="service-card-title">
                <a href="<?= $serviceUrl ?>">
                    <?= htmlspecialchars($service["title"]) ?>
                </a>
            </h3>
            
            <div class="service-card-freelancer">
                <img src="<?= $profilePhoto ?>" 
                     alt="<?= htmlspecialchars($service["first_name"] ?? '') ?>">
                <span><?= formatFullName($service["first_name"] ?? '', $service["last_name"] ?? '') ?></span>
            </div>
            
            <p class="service-card-category"><?= htmlspecialchars($service["category"] ?? '') ?></p>
            
            <span class="service-card-price">
                <?= $pricePrefix ?><?= formatPrice($service["price"]) ?>
            </span>
        </div>
    </div>
    <?php
}

