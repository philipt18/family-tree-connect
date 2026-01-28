<?php
/**
 * Dashboard Template
 * 
 * @package FamilyTreeConnect
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ftc-dashboard">
    <h1><?php _e('My Family Trees', 'family-tree-connect'); ?></h1>
    
    <!-- Trees -->
    <div class="ftc-dashboard-section">
        <div class="ftc-dashboard-header">
            <h2><?php _e('Trees', 'family-tree-connect'); ?></h2>
            <button class="ftc-btn ftc-btn-primary ftc-create-tree">
                <?php _e('Create New Tree', 'family-tree-connect'); ?>
            </button>
        </div>
        
        <?php if (!empty($trees)) : ?>
            <div class="ftc-trees-grid">
                <?php foreach ($trees as $tree) : ?>
                    <div class="ftc-tree-card">
                        <h3><?php echo esc_html($tree->name); ?></h3>
                        <?php if ($tree->description) : ?>
                            <p><?php echo esc_html($tree->description); ?></p>
                        <?php endif; ?>
                        <div class="ftc-tree-card-actions">
                            <a href="<?php echo esc_url(add_query_arg('tree_id', $tree->id, home_url('family-tree/'))); ?>" class="ftc-btn ftc-btn-outline">
                                <?php _e('View', 'family-tree-connect'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <p><?php _e('You haven\'t created any family trees yet.', 'family-tree-connect'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Pending Merge Requests -->
    <?php if (!empty($merge_requests)) : ?>
        <div class="ftc-dashboard-section">
            <h2><?php _e('Pending Merge Requests', 'family-tree-connect'); ?></h2>
            <div class="ftc-merge-requests">
                <?php foreach ($merge_requests as $request) : ?>
                    <div class="ftc-merge-request-card">
                        <div class="ftc-merge-request-info">
                            <strong><?php echo esc_html($request->requesting_user->display_name); ?></strong>
                            <?php _e('wants to merge', 'family-tree-connect'); ?>
                            <strong><?php echo esc_html($request->source_person->display_name); ?></strong>
                            <?php _e('with', 'family-tree-connect'); ?>
                            <strong><?php echo esc_html($request->target_person->display_name); ?></strong>
                        </div>
                        <?php if ($request->message) : ?>
                            <p class="ftc-merge-request-message"><?php echo esc_html($request->message); ?></p>
                        <?php endif; ?>
                        <div class="ftc-merge-request-actions">
                            <button class="ftc-btn ftc-btn-primary ftc-approve-merge" data-request-id="<?php echo esc_attr($request->id); ?>">
                                <?php _e('Approve', 'family-tree-connect'); ?>
                            </button>
                            <button class="ftc-btn ftc-btn-danger ftc-reject-merge" data-request-id="<?php echo esc_attr($request->id); ?>">
                                <?php _e('Reject', 'family-tree-connect'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Face Suggestions -->
    <?php if (!empty($face_suggestions)) : ?>
        <div class="ftc-dashboard-section">
            <h2><?php _e('Face Identification Suggestions', 'family-tree-connect'); ?></h2>
            <div class="ftc-face-suggestions">
                <?php foreach ($face_suggestions as $face) : ?>
                    <div class="ftc-face-suggestion-card">
                        <div class="ftc-face-image">
                            <!-- Face thumbnail would go here -->
                        </div>
                        <div class="ftc-face-info">
                            <p><?php _e('Is this', 'family-tree-connect'); ?> <strong><?php echo esc_html($face->suggested_person->display_name ?? '?'); ?></strong>?</p>
                            <p class="ftc-face-confidence"><?php printf(__('Confidence: %d%%', 'family-tree-connect'), round($face->confidence * 100)); ?></p>
                        </div>
                        <div class="ftc-face-actions">
                            <button class="ftc-btn ftc-btn-primary ftc-confirm-face" data-face-id="<?php echo esc_attr($face->id); ?>" data-confirm="true">
                                <?php _e('Yes', 'family-tree-connect'); ?>
                            </button>
                            <button class="ftc-btn ftc-btn-secondary ftc-confirm-face" data-face-id="<?php echo esc_attr($face->id); ?>" data-confirm="false">
                                <?php _e('No', 'family-tree-connect'); ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Recent Notifications -->
    <?php if (!empty($notifications)) : ?>
        <div class="ftc-dashboard-section">
            <h2><?php _e('Recent Notifications', 'family-tree-connect'); ?></h2>
            <div class="ftc-notifications-list">
                <?php foreach ($notifications as $notification) : ?>
                    <div class="ftc-notification-item <?php echo $notification->is_read ? 'read' : 'unread'; ?>" data-id="<?php echo esc_attr($notification->id); ?>">
                        <div class="ftc-notification-content">
                            <strong><?php echo esc_html($notification->title); ?></strong>
                            <p><?php echo esc_html($notification->message); ?></p>
                            <span class="ftc-notification-date"><?php echo esc_html(human_time_diff(strtotime($notification->created_at))); ?> <?php _e('ago', 'family-tree-connect'); ?></span>
                        </div>
                        <?php if ($notification->link) : ?>
                            <a href="<?php echo esc_url($notification->link); ?>" class="ftc-btn ftc-btn-outline ftc-btn-small">
                                <?php _e('View', 'family-tree-connect'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.ftc-dashboard-section { margin-bottom: 40px; }
.ftc-dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.ftc-trees-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.ftc-tree-card { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.ftc-tree-card h3 { margin: 0 0 10px; }
.ftc-tree-card p { color: #666; margin-bottom: 15px; }
.ftc-merge-request-card, .ftc-face-suggestion-card, .ftc-notification-item { 
    background: #fff; border-radius: 8px; padding: 15px; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.ftc-merge-request-actions, .ftc-face-actions { display: flex; gap: 10px; margin-top: 15px; }
.ftc-notification-item.unread { border-left: 4px solid var(--ftc-primary); }
.ftc-notification-date { font-size: 12px; color: #999; }
</style>
