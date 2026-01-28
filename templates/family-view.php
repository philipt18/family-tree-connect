<?php
/**
 * Family View Template
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ftc-family-view">
    <h2><?php echo esc_html($family->display_title); ?></h2>
    
    <?php if ($family->marriage_date) : ?>
        <p class="ftc-family-marriage">
            <?php _e('Married:', 'family-tree-connect'); ?> 
            <?php echo esc_html(FTC_Calendar::format_date($family->marriage_date, $family->marriage_date_calendar)); ?>
            <?php if ($family->marriage_location) : ?>
                - <?php echo esc_html($family->marriage_location); ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    
    <!-- Grandparents -->
    <?php if (!empty($family_view['grandparents'])) : ?>
        <div class="ftc-family-view-grandparents">
            <?php if (!empty($family_view['grandparents']['paternal'])) : ?>
                <div class="ftc-family-view-grandparent-group">
                    <h4><?php _e('Paternal Grandparents', 'family-tree-connect'); ?></h4>
                    <div class="ftc-family-members">
                        <?php foreach ($family_view['grandparents']['paternal'] as $gp) : ?>
                            <a href="<?php echo esc_url(add_query_arg('ftc_person', $gp->uuid, home_url('family-tree/person/'))); ?>" class="ftc-person-card <?php echo esc_attr($gp->gender); ?>">
                                <div class="ftc-person-card-photo">
                                    <?php if ($gp->default_photo_url) : ?>
                                        <img src="<?php echo esc_url($gp->default_photo_url); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="ftc-person-card-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($gp, 'short')); ?></div>
                                <?php if ($gp->birth_date) : ?>
                                    <div class="ftc-person-card-dates"><?php echo esc_html(substr($gp->birth_date, 0, 4)); ?></div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($family_view['grandparents']['maternal'])) : ?>
                <div class="ftc-family-view-grandparent-group">
                    <h4><?php _e('Maternal Grandparents', 'family-tree-connect'); ?></h4>
                    <div class="ftc-family-members">
                        <?php foreach ($family_view['grandparents']['maternal'] as $gp) : ?>
                            <a href="<?php echo esc_url(add_query_arg('ftc_person', $gp->uuid, home_url('family-tree/person/'))); ?>" class="ftc-person-card <?php echo esc_attr($gp->gender); ?>">
                                <div class="ftc-person-card-photo">
                                    <?php if ($gp->default_photo_url) : ?>
                                        <img src="<?php echo esc_url($gp->default_photo_url); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="ftc-person-card-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($gp, 'short')); ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Parents -->
    <div class="ftc-family-view-parents">
        <?php if ($family->spouse1) : ?>
            <a href="<?php echo esc_url(add_query_arg('ftc_person', $family->spouse1->uuid, home_url('family-tree/person/'))); ?>" class="ftc-person-card <?php echo esc_attr($family->spouse1->gender); ?>">
                <div class="ftc-person-card-photo">
                    <?php if ($family->spouse1->default_photo_url) : ?>
                        <img src="<?php echo esc_url($family->spouse1->default_photo_url); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="ftc-person-card-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($family->spouse1)); ?></div>
                <?php if ($family->spouse1->birth_date || $family->spouse1->death_date) : ?>
                    <div class="ftc-person-card-dates">
                        <?php echo esc_html(($family->spouse1->birth_date ? substr($family->spouse1->birth_date, 0, 4) : '?') . ' - ' . ($family->spouse1->death_date ? substr($family->spouse1->death_date, 0, 4) : '')); ?>
                    </div>
                <?php endif; ?>
            </a>
        <?php else : ?>
            <button class="ftc-person-card ftc-add-person-card ftc-add-spouse" data-family-id="<?php echo esc_attr($family->id); ?>">
                <span>+</span>
                <div class="ftc-person-card-name"><?php _e('Add Spouse', 'family-tree-connect'); ?></div>
            </button>
        <?php endif; ?>
        
        <?php if ($family->spouse2) : ?>
            <a href="<?php echo esc_url(add_query_arg('ftc_person', $family->spouse2->uuid, home_url('family-tree/person/'))); ?>" class="ftc-person-card <?php echo esc_attr($family->spouse2->gender); ?>">
                <div class="ftc-person-card-photo">
                    <?php if ($family->spouse2->default_photo_url) : ?>
                        <img src="<?php echo esc_url($family->spouse2->default_photo_url); ?>" alt="">
                    <?php endif; ?>
                </div>
                <div class="ftc-person-card-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($family->spouse2)); ?></div>
                <?php if ($family->spouse2->birth_date || $family->spouse2->death_date) : ?>
                    <div class="ftc-person-card-dates">
                        <?php echo esc_html(($family->spouse2->birth_date ? substr($family->spouse2->birth_date, 0, 4) : '?') . ' - ' . ($family->spouse2->death_date ? substr($family->spouse2->death_date, 0, 4) : '')); ?>
                    </div>
                <?php endif; ?>
            </a>
        <?php else : ?>
            <button class="ftc-person-card ftc-add-person-card ftc-add-spouse" data-family-id="<?php echo esc_attr($family->id); ?>">
                <span>+</span>
                <div class="ftc-person-card-name"><?php _e('Add Spouse', 'family-tree-connect'); ?></div>
            </button>
        <?php endif; ?>
    </div>
    
    <!-- Children -->
    <div class="ftc-family-view-children">
        <h3><?php _e('Children', 'family-tree-connect'); ?></h3>
        <div class="ftc-family-members" style="justify-content: center;">
            <?php if (!empty($family->children)) : ?>
                <?php foreach ($family->children as $child) : ?>
                    <a href="<?php echo esc_url(add_query_arg('ftc_person', $child->uuid, home_url('family-tree/person/'))); ?>" class="ftc-person-card <?php echo esc_attr($child->gender); ?>">
                        <div class="ftc-person-card-photo">
                            <?php if ($child->default_photo_url) : ?>
                                <img src="<?php echo esc_url($child->default_photo_url); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <div class="ftc-person-card-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($child, 'short')); ?></div>
                        <?php if ($child->birth_date) : ?>
                            <div class="ftc-person-card-dates"><?php echo esc_html(substr($child->birth_date, 0, 4)); ?></div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <button class="ftc-person-card ftc-add-person-card ftc-add-child" data-family-id="<?php echo esc_attr($family->id); ?>">
                <span>+</span>
                <div class="ftc-person-card-name"><?php _e('Add Child', 'family-tree-connect'); ?></div>
            </button>
        </div>
    </div>
</div>
