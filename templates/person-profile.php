<?php
/**
 * Person Profile Template
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ftc-person-profile" data-person-id="<?php echo esc_attr($person->id); ?>">
    <div class="ftc-person-header">
        <div class="ftc-person-photo">
            <?php if ($person->default_photo_url) : ?>
                <img src="<?php echo esc_url($person->default_photo_url); ?>" alt="<?php echo esc_attr($person->display_name); ?>">
            <?php else : ?>
                <div class="ftc-person-photo-placeholder">
                    <?php echo esc_html(mb_substr($person->first_name, 0, 1)); ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="ftc-person-info">
            <h1><?php echo esc_html($person->display_name); ?></h1>
            
            <div class="ftc-person-dates">
                <?php if ($person->birth_date) : ?>
                    <span><?php echo esc_html(FTC_Calendar::format_date($person->birth_date, $person->birth_date_calendar, $person->birth_date_approximate)); ?></span>
                    <?php if ($person->birth_location) : ?>
                        <span> - <?php echo esc_html($person->birth_location); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php if ($person->death_date) : ?>
                    <br>
                    <span><?php _e('Died:', 'family-tree-connect'); ?> <?php echo esc_html(FTC_Calendar::format_date($person->death_date, $person->death_date_calendar, $person->death_date_approximate)); ?></span>
                    <?php if ($person->death_location) : ?>
                        <span> - <?php echo esc_html($person->death_location); ?></span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($person->relationship_to_source) : ?>
                <div class="ftc-person-relationship">
                    <?php echo esc_html($person->relationship_to_source); ?>
                </div>
            <?php endif; ?>
            
            <?php if (FTC_Core::can_edit_person($person->id)) : ?>
                <div class="ftc-person-actions" style="margin-top: 15px;">
                    <button class="ftc-btn ftc-btn-outline ftc-edit-person" data-person-id="<?php echo esc_attr($person->id); ?>">
                        <?php _e('Edit', 'family-tree-connect'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="ftc-person-body">
        <!-- Basic Info -->
        <div class="ftc-person-section">
            <h2><?php _e('Information', 'family-tree-connect'); ?></h2>
            <div class="ftc-info-grid">
                <?php if ($person->maiden_name) : ?>
                    <div class="ftc-info-item">
                        <div class="ftc-info-label"><?php _e('Maiden Name', 'family-tree-connect'); ?></div>
                        <div class="ftc-info-value"><?php echo esc_html($person->maiden_name); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($person->nickname) : ?>
                    <div class="ftc-info-item">
                        <div class="ftc-info-label"><?php _e('Nickname', 'family-tree-connect'); ?></div>
                        <div class="ftc-info-value"><?php echo esc_html($person->nickname); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if ($person->occupation) : ?>
                    <div class="ftc-info-item">
                        <div class="ftc-info-label"><?php _e('Occupation', 'family-tree-connect'); ?></div>
                        <div class="ftc-info-value"><?php echo esc_html($person->occupation); ?></div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($person->custom_fields)) : ?>
                    <?php foreach ($person->custom_fields as $field_name => $field_data) : ?>
                        <div class="ftc-info-item">
                            <div class="ftc-info-label"><?php echo esc_html($field_data['label']); ?></div>
                            <div class="ftc-info-value"><?php echo esc_html($field_data['value']); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($person->biography) : ?>
                <div class="ftc-person-biography" style="margin-top: 20px;">
                    <h3><?php _e('Biography', 'family-tree-connect'); ?></h3>
                    <p><?php echo nl2br(esc_html($person->biography)); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Family -->
        <div class="ftc-person-section">
            <h2><?php _e('Family', 'family-tree-connect'); ?></h2>
            
            <?php if ($parents['father'] || $parents['mother']) : ?>
                <h3><?php _e('Parents', 'family-tree-connect'); ?></h3>
                <div class="ftc-family-members">
                    <?php if ($parents['father']) : ?>
                        <a href="<?php echo esc_url(add_query_arg('ftc_person', $parents['father']->uuid, home_url('family-tree/person/'))); ?>" class="ftc-family-member">
                            <div class="ftc-family-member-photo">
                                <?php if ($parents['father']->default_photo_url) : ?>
                                    <img src="<?php echo esc_url($parents['father']->default_photo_url); ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="ftc-family-member-info">
                                <div class="ftc-family-member-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($parents['father'])); ?></div>
                                <div class="ftc-family-member-relation"><?php _e('Father', 'family-tree-connect'); ?></div>
                            </div>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($parents['mother']) : ?>
                        <a href="<?php echo esc_url(add_query_arg('ftc_person', $parents['mother']->uuid, home_url('family-tree/person/'))); ?>" class="ftc-family-member">
                            <div class="ftc-family-member-photo">
                                <?php if ($parents['mother']->default_photo_url) : ?>
                                    <img src="<?php echo esc_url($parents['mother']->default_photo_url); ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="ftc-family-member-info">
                                <div class="ftc-family-member-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($parents['mother'])); ?></div>
                                <div class="ftc-family-member-relation"><?php _e('Mother', 'family-tree-connect'); ?></div>
                            </div>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($spouses)) : ?>
                <h3><?php _e('Spouses', 'family-tree-connect'); ?></h3>
                <div class="ftc-family-members">
                    <?php foreach ($spouses as $spouse) : ?>
                        <a href="<?php echo esc_url(add_query_arg('ftc_person', $spouse->uuid, home_url('family-tree/person/'))); ?>" class="ftc-family-member">
                            <div class="ftc-family-member-photo">
                                <?php if ($spouse->default_photo_url) : ?>
                                    <img src="<?php echo esc_url($spouse->default_photo_url); ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="ftc-family-member-info">
                                <div class="ftc-family-member-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($spouse)); ?></div>
                                <div class="ftc-family-member-relation"><?php echo $spouse->gender === 'female' ? __('Wife', 'family-tree-connect') : __('Husband', 'family-tree-connect'); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($children)) : ?>
                <h3><?php _e('Children', 'family-tree-connect'); ?></h3>
                <div class="ftc-family-members">
                    <?php foreach ($children as $child) : ?>
                        <a href="<?php echo esc_url(add_query_arg('ftc_person', $child->uuid, home_url('family-tree/person/'))); ?>" class="ftc-family-member">
                            <div class="ftc-family-member-photo">
                                <?php if ($child->default_photo_url) : ?>
                                    <img src="<?php echo esc_url($child->default_photo_url); ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="ftc-family-member-info">
                                <div class="ftc-family-member-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($child)); ?></div>
                                <div class="ftc-family-member-relation"><?php echo $child->gender === 'female' ? __('Daughter', 'family-tree-connect') : __('Son', 'family-tree-connect'); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($siblings)) : ?>
                <h3><?php _e('Siblings', 'family-tree-connect'); ?></h3>
                <div class="ftc-family-members">
                    <?php foreach ($siblings as $sibling) : ?>
                        <a href="<?php echo esc_url(add_query_arg('ftc_person', $sibling->uuid, home_url('family-tree/person/'))); ?>" class="ftc-family-member">
                            <div class="ftc-family-member-photo">
                                <?php if ($sibling->default_photo_url) : ?>
                                    <img src="<?php echo esc_url($sibling->default_photo_url); ?>" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="ftc-family-member-info">
                                <div class="ftc-family-member-name"><?php echo esc_html(FTC_Core::get_instance()->person->get_display_name($sibling)); ?></div>
                                <div class="ftc-family-member-relation"><?php echo $sibling->gender === 'female' ? __('Sister', 'family-tree-connect') : __('Brother', 'family-tree-connect'); ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Timeline -->
        <?php 
        $timeline = FTC_Core::get_instance()->event->get_timeline($person->id);
        if (!empty($timeline)) : 
        ?>
            <div class="ftc-person-section">
                <h2><?php _e('Timeline', 'family-tree-connect'); ?></h2>
                <div class="ftc-timeline">
                    <?php foreach ($timeline as $event) : ?>
                        <div class="ftc-timeline-item">
                            <div class="ftc-timeline-date"><?php echo esc_html($event['date']); ?></div>
                            <div class="ftc-timeline-title"><?php echo esc_html($event['label']); ?></div>
                            <?php if ($event['location']) : ?>
                                <div class="ftc-timeline-location"><?php echo esc_html($event['location']); ?></div>
                            <?php endif; ?>
                            <?php if ($event['description']) : ?>
                                <div class="ftc-timeline-description"><?php echo esc_html($event['description']); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Media -->
        <?php if ($atts['show_media']) : 
            $media = FTC_Core::get_instance()->media->get_by_person($person->id);
            if (!empty($media)) :
        ?>
            <div class="ftc-person-section">
                <h2><?php _e('Photos & Documents', 'family-tree-connect'); ?></h2>
                <div class="ftc-media-gallery">
                    <?php foreach ($media as $item) : ?>
                        <div class="ftc-media-item" data-title="<?php echo esc_attr($item->title); ?>">
                            <?php if ($item->media_category === 'photo') : ?>
                                <img src="<?php echo esc_url($item->medium_url ?: $item->url); ?>" alt="<?php echo esc_attr($item->title); ?>">
                            <?php else : ?>
                                <div class="ftc-media-document">
                                    <span class="ftc-media-icon">📄</span>
                                    <span class="ftc-media-title"><?php echo esc_html($item->title); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="ftc-media-item-overlay">
                                <?php echo esc_html($item->title); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if (FTC_Core::can_edit_person($person->id)) : ?>
                    <div style="margin-top: 20px;">
                        <button class="ftc-btn ftc-btn-outline ftc-upload-media" data-person-id="<?php echo esc_attr($person->id); ?>">
                            <?php _e('Add Photos', 'family-tree-connect'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; endif; ?>
    </div>
</div>
