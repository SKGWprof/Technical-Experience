/**
 * Project System: Fluent Forms Integration, CPT, and Dynamic Filtering
 *
 * This single snippet handles:
 * 1. Registers the 'User Post' Custom Post Type.
 * 2. Hooks into Fluent Forms ID 3 (Poster's form) to create a pending post.
 * 3. Registers the [display_user_posts] shortcode, which now filters content
 * based on a WordPress Transient (Viewer's data from Form ID 1).
 * 4. Implements AJAX handler for the "Team Up / Contact" email button.
 * - NEW: Includes logic to auto-delete the post when the participant tally reaches the preferred number.
 * 5. Implements AJAX handler for Post Deletion with Email Verification.
 * 6. Includes Tally and Grade information in contact emails.
 */

// =================================================================
// 1. CPT REGISTRATION (Creates the "User Posts" Admin Menu)
// =================================================================

function register_user_posts_cpt() {
    $labels = array(
        'name'               => 'User Posts',
        'singular_name'      => 'User Post',
        'menu_name'          => 'User Posts',
        'add_new'            => 'Add New',
        'add_new_item'       => 'Add New User Post',
        'edit_item'          => 'Edit User Post',
        'all_items'          => 'All User Posts',
    );
    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'has_archive'        => true,
        'publicly_queryable' => true,
        'show_in_rest'       => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'menu_icon'          => 'dashicons-format-aside',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
    );
    register_post_type( 'user_post', $args );
}
add_action( 'after_setup_theme', 'register_user_posts_cpt' );


// =================================================================
// 2. FLUENT FORMS HOOK (Saves submission data from POSTER's Form ID 3)
// =================================================================

add_action('fluentform/submission_inserted', 'create_post_from_fluent_form', 10, 3);

function create_post_from_fluent_form($entry_id, $formData, $form) {
    // TARGET: Poster's Form ID 3
    $target_form_id = 3;

    if ( (int)$form->id !== $target_form_id ) {
        return;
    }

    // --- MAPPING POSTER's FIELDS TO POST METADATA ---
    
    // Using the correct Fluent Forms Name Element keys: 'first_name' and 'last_name'
    $first_name = sanitize_text_field( $formData['names']['first_name'] ?? '' );
    $last_name = sanitize_text_field( $formData['names']['last_name'] ?? '' );
    $full_name = trim($first_name . ' ' . $last_name);
    
    // Fallback if the standard Fluent Name Element format is missing, check for a simple 'full_name' field
    if (empty($full_name)) {
        $full_name = sanitize_text_field( $formData['full_name'] ?? '' );
    }
    
    $post_title = sanitize_text_field( $formData['project_title'] ?? 'Untitled Project' );
    $post_content = wp_kses_post( $formData['project_description'] ?? '' );
    
    // Core fields for filtering
    $poster_grade = sanitize_text_field( $formData['your_grade'] ?? '' );
    $poster_gender = sanitize_text_field( $formData['your_gender'] ?? '' );
    $preferred_audience_grade = $formData['preferred_audience_grade'] ?? []; 
    $preferred_gender_poster = sanitize_text_field( $formData['preferred_gender'] ?? '' );
    $preferred_number = sanitize_text_field( $formData['preferred_number'] ?? '' ); // Used for tally capacity

    // Fields for contact
    $poster_email = sanitize_email( $formData['email'] ?? '' );

    $new_post = array(
        'post_title'    => $post_title,
        'post_content'  => $post_content,
        'post_status'   => 'publish', 
        'post_type'     => 'user_post',
    );

    $post_id = wp_insert_post( $new_post );

    if ( ! is_wp_error( $post_id ) && $post_id > 0 ) {
        // Save fields required for filtering and grade retrieval
        update_post_meta( $post_id, 'ff_poster_grade', $poster_grade );
        update_post_meta( $post_id, 'ff_poster_gender', $poster_gender );
        update_post_meta( $post_id, 'ff_preferred_gender', $preferred_gender_poster ); 
        update_post_meta( $post_id, 'ff_preferred_number', $preferred_number );
        
        // Save audience grades as JSON (for LIKE query filtering)
        update_post_meta( $post_id, 'ff_preferred_audience_grade', json_encode($preferred_audience_grade) ); 
        
        // Save metadata required for contact AND deletion 
        update_post_meta( $post_id, 'ff_full_name', $full_name );
        update_post_meta( $post_id, 'ff_user_email', $poster_email );
        update_post_meta( $post_id, 'ff_original_entry_id', $entry_id );

        // Initialize participant count to 0
        update_post_meta( $post_id, 'ff_current_participants', 0 ); 
    }
}

// =================================================================
// 3. AJAX HANDLER (Sends Email to Poster & Updates Tally & Auto-Deletes)
// =================================================================

add_action('wp_ajax_send_team_up_email', 'handle_team_up_email');
add_action('wp_ajax_nopriv_send_team_up_email', 'handle_team_up_email');

function handle_team_up_email() {
    // 1. Security check and Input Validation
    if ( ! isset($_POST['post_id']) || ! isset($_POST['message']) ) {
        wp_send_json_error( array('message' => 'Missing required data.') );
    }
    
    $post_id = intval($_POST['post_id']);
    // Sanitize message using wp_kses_post for safety, but allow basic text formatting
    $user_message = wp_kses_post(sanitize_text_field(stripslashes($_POST['message'])));
    
    // 2. Retrieve Poster's Details (From Post Metadata)
    $poster_email = get_post_meta( $post_id, 'ff_user_email', true );
    $poster_name = get_post_meta( $post_id, 'ff_full_name', true );
    $poster_grade = get_post_meta( $post_id, 'ff_poster_grade', true ); // <-- POSTER GRADE
    $post_title = get_the_title($post_id);

    // Fallback if poster name is empty, use a generic fallback. 
    if (empty($poster_name)) {
        $poster_name = 'The poster of ' . esc_html($post_title); 
    }
    
    if ( ! is_email($poster_email) ) {
        wp_send_json_error( array('message' => 'Error: Post maker email address is invalid.') );
    }
    if ( empty($post_title) ) {
        wp_send_json_error( array('message' => 'Error: Could not retrieve post title.') );
    }

    // 3. Retrieve Sender's Details (From Transient)
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
    $transient_key = 'viewer_filters_' . sanitize_key($ip_address);
    $viewer_filters = get_transient($transient_key);

    // Check 1: Transient session expired/missing
    if ( false === $viewer_filters || empty($viewer_filters) ) {
        wp_send_json_error( array('message' => 'Error: Your session data has expired. Please refresh the page and complete the Profile Form (ID 1) again to set up your contact information.') );
    }

    $sender_name = $viewer_filters['v_name'] ?? '';
    $sender_email = $viewer_filters['v_email'] ?? '';
    $sender_grade = $viewer_filters['v_grade'] ?? 'Not Specified'; // <-- SENDER GRADE
    
    // Check 2: Transient is present, but name/email fields are missing or invalid
    if ( empty($sender_name) || ! is_email($sender_email) ) {
         wp_send_json_error( array('message' => 'Error: Could not retrieve your name or a valid email address from your session data. Please ensure the Name and Email fields in Form 1 were filled correctly.') );
    }
    
    // 4. Format Email Content (To Poster)
    $subject = "Team Up Request for Project: " . esc_html($post_title);
    
    $body = "Hello, " . esc_html($poster_name) . "\n\n";
    $body .= "this email is being sent to tell you that someone would like to team up with you in completing the project (" . esc_html($post_title) . "), their name and email are given below along with any message they have:\n\n";

    if (!empty($user_message)) {
        $body .= "message: " . $user_message . "\n";
    }
    
    $body .= "their email: " . esc_html($sender_email) . "\n";
    $body .= "their name: " . esc_html($sender_name) . "\n";
    $body .= "their grade: " . esc_html($sender_grade) . "\n\n"; // <-- ADDED SENDER GRADE
    
    $headers = array(
        "From: " . esc_html($sender_name) . " <" . esc_html($sender_email) . ">",
        'Content-Type: text/plain; charset=UTF-8'
    );
    
    // 5. Send Email (To Poster)
    $mail_sent = wp_mail( $poster_email, $subject, $body, $headers );

    if ($mail_sent) {
        // --- Email to Sender (Viewer) ---
        $site_domain = parse_url(get_site_url(), PHP_URL_HOST);
        $sender_subject = "Contact Information for Project: " . esc_html($post_title);
        
        // Email body sent to the SENDER (Viewer)
        $sender_body = "Hello, this email is to inform you of the contact info for the person behind the project: " . esc_html($post_title) . "\n\n";
        $sender_body .= "their email: " . esc_html($poster_email) . "\n";
        $sender_body .= "their name: " . esc_html($poster_name) . "\n"; 
        $sender_body .= "their grade: " . esc_html($poster_grade) . "\n\n"; // <-- ADDED POSTER GRADE
        $sender_body .= "please do contact them soon.";
        
        // Headers for the confirmation email (sent from the site/admin)
        $sender_headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: No Reply <no-reply@' . esc_html($site_domain) . '>',
        );
        
        // Send the confirmation email to the viewer/sender
        wp_mail( $sender_email, $sender_subject, $sender_body, $sender_headers );

        
        // --- Tally Update & DELETION Logic ---
        $current_count = intval(get_post_meta($post_id, 'ff_current_participants', true));
        $preferred_number = get_post_meta($post_id, 'ff_preferred_number', true); 

        // Extract numerical value from preferred_number (e.g., "3 people" -> 3)
        $preferred_number_int = intval(preg_replace('/[^0-9]/', '', $preferred_number));
        
        // Safety check: ensure minimum capacity is 1
        if ($preferred_number_int < 1) {
            $preferred_number_int = 1; 
        }

        // Generic success message
        $success_message = 'An email has been successfully sent to the project poster containing your contact info. An email has also been sent to you containing the poster\'s contact info. (check your spam folders)';
        $post_deleted_flag = false; // Flag to tell JS to reload

        if ($current_count < $preferred_number_int) {
            $new_count = $current_count + 1;
            update_post_meta($post_id, 'ff_current_participants', $new_count);

            // CHECK FOR AUTO-DELETION (New count equals preferred capacity)
            if ($new_count === $preferred_number_int) {
                // Capacity reached! Process deletion.
                $delete_result = wp_delete_post( $post_id, true );
                
                if ( ! is_wp_error( $delete_result ) ) {
                    // Success: Set flag for JS, but keep $success_message generic
                    $post_deleted_flag = true; 
                } else {
                    // Deletion failed, but message went through. Log error silently.
                    error_log("Auto-deletion failed for post $post_id: " . $delete_result->get_error_message());
                }
            }
        } else {
            // Should not happen if frontend is working, but keeps server logic robust.
            // Keep generic message.
        }

        wp_send_json_success( array(
            'message' => $success_message,
            'post_deleted' => $post_deleted_flag // Pass the new flag to JS
        ) );
    } else {
        // This usually indicates a server-side mail configuration issue, but we report failure to the user.
        wp_send_json_error( array('message' => 'Failed to send email. Please check your WordPress email settings or try again.') );
    }
}

// =================================================================
// 4. AJAX HANDLER (Deletes Post after Email Verification)
// =================================================================

add_action('wp_ajax_delete_user_post', 'handle_delete_user_post');
add_action('wp_ajax_nopriv_delete_user_post', 'handle_delete_user_post');

function handle_delete_user_post() {
    if ( ! isset($_POST['post_id']) || ! isset($_POST['email']) ) {
        wp_send_json_error( array('message' => 'Missing post ID or email.') );
    }

    $post_id = intval($_POST['post_id']);
    $provided_email = sanitize_email($_POST['email']);

    if ( ! is_email($provided_email) ) {
        wp_send_json_error( array('message' => 'Please enter a valid email address.') );
    }

    // 1. Get the email stored in the post metadata
    $poster_email = get_post_meta( $post_id, 'ff_user_email', true );

    if ( empty($poster_email) ) {
        wp_send_json_error( array('message' => 'Error: Could not retrieve poster\'s email for this project.') );
    }

    // 2. Compare the provided email with the stored email (case-insensitive for robustness)
    if ( strtolower($provided_email) !== strtolower($poster_email) ) {
        wp_send_json_error( array('message' => 'The email provided does not match the email associated with this project. Deletion failed.') );
    }

    // 3. Email matched, proceed with permanent deletion (true)
    $result = wp_delete_post( $post_id, true ); 

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array('message' => 'An error occurred during post deletion: ' . $result->get_error_message()) );
    }

    // Success response
    wp_send_json_success( array('message' => 'Project "' . get_the_title($post_id) . '" has been successfully deleted.') );
}


// =================================================================
// 5. SHORTCODE DEFINITION (Displays & FILTERS published posts)
// =================================================================

function display_user_posts_func( $atts ) {
    // --- 5A. Get VIEWER Data from Transient ---
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
    $transient_key = 'viewer_filters_' . sanitize_key($ip_address);
    $viewer_filters = get_transient($transient_key);

    // Default values if no transient is set
    $viewer_grade = $viewer_filters['v_grade'] ?? '';
    $viewer_gender = $viewer_filters['v_gender'] ?? '';
    $viewer_preferred_grades_list = $viewer_filters['v_pref_grades'] ?? '';
    $viewer_preferred_partner_gender = $viewer_filters['v_pref_partner_gender'] ?? '';

    // --- 5B. Get Total Post Count (Unfiltered) ---
    $total_posts_args = array(
        'post_type'      => 'user_post',
        'post_status'    => 'publish',
        'posts_per_page' => -1, // Use -1 to get all, but count found_posts is better
        'fields'         => 'ids',
    );
    $total_posts_query = new WP_Query( $total_posts_args );
    $total_posts = $total_posts_query->found_posts;
    wp_reset_postdata();

    
    $args = array(
        'post_type'      => 'user_post',
        'post_status'    => 'publish', 
        'posts_per_page' => -1, // Show all published posts
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    
    $meta_query = array( 'relation' => 'AND' );
    $is_filtered = false;

    // --- 5C. FILTERING RULES (Based on Viewer's Transient Data) ---

    // RULE 1: Viewer's Grade MUST be in Poster's preferred audience grades.
    if ( ! empty( $viewer_grade ) ) {
        $is_filtered = true;
        // The value is stored as JSON ["Grade X", "Grade Y"], so we use LIKE
        $meta_query[] = array(
            'key'     => 'ff_preferred_audience_grade',
            'value'   => '"' . $viewer_grade . '"', // Search for the exact grade string inside the JSON array
            'compare' => 'LIKE',
        );
    }

    // RULE 2: Poster's Preferred Audience Gender vs. Viewer's Gender.
    if ( ! empty( $viewer_gender ) ) {
        $is_filtered = true;
        $gender_clause = array('relation' => 'OR');
        
        // 2a. Always include posts where the poster accepts 'both'
        $gender_clause[] = array(
            'key'     => 'ff_preferred_gender',
            'value'   => 'both',
            'compare' => '=',
        );

        // 2b. Include posts where the viewer matches the poster's preference
        $viewer_gender_lower = strtolower($viewer_gender);
        
        if ( $viewer_gender_lower === 'girl' ) {
             $gender_clause[] = array(
                'key'     => 'ff_preferred_gender',
                'value'   => 'only girls',
                'compare' => '=',
            );
        } elseif ( $viewer_gender_lower === 'boy' ) {
             $gender_clause[] = array(
                'key'     => 'ff_preferred_gender',
                'value'   => 'only boys',
                'compare' => '=',
            );
        }
        $meta_query[] = $gender_clause;
    }

    // RULE 3: Poster's Grade MUST be in Viewer's preferred grades.
    if ( ! empty( $viewer_preferred_grades_list ) ) {
        $is_filtered = true;
        $preferred_grades_array = array_map('trim', explode(',', $viewer_preferred_grades_list));
        
        $grade_clause = array('relation' => 'OR');

        foreach ($preferred_grades_array as $grade) {
            $grade_clause[] = array(
                'key'     => 'ff_poster_grade',
                'value'   => $grade,
                'compare' => '=',
            );
        }
        
        if ( count($grade_clause) > 1 ) {
            $meta_query[] = $grade_clause;
        }
    }
    
    // RULE 4: Poster's Gender MUST match Viewer's Preferred Partner Gender
    if ( ! empty( $viewer_preferred_partner_gender ) ) { 
        $is_filtered = true;
        $viewer_pref_partner = strtolower($viewer_preferred_partner_gender);

        $poster_gender_clause = array('relation' => 'OR');

        if ($viewer_pref_partner === 'both') {
            // Viewer prefers 'both', so we allow any post made by a boy or a girl.
            $poster_gender_clause[] = array(
                'key'     => 'ff_poster_gender',
                'value'   => array('girl', 'boy'),
                'compare' => 'IN',
            );
        } else {
            // Viewer prefers 'only boys' or 'only girls'. 
            // Poster's actual gender must match the viewer's preference.
            $target_gender = ($viewer_pref_partner === 'only girls') ? 'girl' : 'boy';
            
            $poster_gender_clause[] = array(
                'key'     => 'ff_poster_gender',
                'value'   => $target_gender,
                'compare' => '=',
            );
        }
        
        // Add the gender clause only if it contains filter logic
        if ( count($poster_gender_clause) > 1 || 
             (count($poster_gender_clause) === 1 && $viewer_pref_partner !== 'both') ) {
            $meta_query[] = $poster_gender_clause;
        }
    }


    // Apply filters if any are present
    if ( $is_filtered ) {
        // Ensure $meta_query is added only if it has filtering clauses
        $final_meta_query = array('relation' => 'AND');
        $has_clauses = false;
        foreach ($meta_query as $clause) {
            if (is_array($clause) && (isset($clause['key']) || isset($clause['relation']))) {
                $final_meta_query[] = $clause;
                $has_clauses = true;
            }
        }
        
        if ($has_clauses && count($final_meta_query) > 1) {
            $args['meta_query'] = $final_meta_query;
        }
    }

    // --- 5D. RUN FILTERED QUERY AND OUTPUT ---
    $posts_query = new WP_Query( $args );
    $displayed_posts = $posts_query->post_count; // Number of posts displayed
    $output = '';

    // Enqueue jQuery since we are using it for the AJAX call
    wp_enqueue_script('jquery'); 
    
    // Add Google Font link to the header
    wp_enqueue_style('roboto-slab-font', 'https://fonts.googleapis.com/css2?family=Roboto+Slab:wght@500;600;700&display=swap');

    // --- CSS STYLES FOR RESPONSIVENESS AND TRUNCATION AND MODAL ---
    $output .= '<style>
        .user-posts-list {
            display: flex;
            flex-wrap: wrap;
            justify-content: center; /* Center posts horizontally */
            gap: 20px; /* Spacing between items */
        }
        .user-post-item {
            /* Desktop Width (60vw) and Equal Height */
            width: 60vw;
            min-height: 300px; /* Enforce minimum height for consistency */
            
            /* Apply general styles using classes to keep inline styles clean */
            border: 1px solid #ccc; 
            padding: 20px; 
            margin-bottom: 0px; /* Handled by parent gap */
            border-radius: 8px; 
            background-color: #215a89; 
            color: #fdd405;
            font-family: "Roboto Slab", serif; 
            display: flex;
            flex-direction: column;
            position: relative; 
        }
        .post-content-container {
            max-height: 100px; /* Truncation height */
            overflow: hidden;
            transition: max-height 0.4s ease-in-out;
            margin-bottom: 5px;
        }
        .post-content-container.expanded {
            max-height: none !important; /* Overrides max-height when expanded */
            margin-bottom: 25px; /* Restore spacing when fully expanded */
        }
        .show-more-btn {
            background: none;
            border: none;
            color: #fdd405;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
            text-align: left;
            padding: 0;
            margin-bottom: 10px; 
            align-self: flex-start;
            display: none; /* HIDE BY DEFAULT, will be shown by JS only if content overflows */
        }
        .post-content {
            flex-grow: 1; 
        }

        /* --- BUTTON STYLES (Moved inline styles here) --- */
        .contact-poster-btn {
            background-color: #fdd405; 
            color: #215a89; 
            padding: 10px 18px; 
            border: none; 
            border-radius: 4px; 
            cursor: pointer; 
            font-weight: 700; 
            font-size: 0.9em;
            transition: background-color 0.2s;
        }
        .contact-poster-btn:hover {
            background-color: #ffea60;
        }
        
        /* --- MODAL STYLES (General Contact Modal) --- */
        .modal-overlay {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .contact-modal {
            background-color: #215a89;
            color: #fdd405;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            width: 90%;
            max-width: 500px;
            font-family: "Roboto Slab", serif;
            position: relative;
        }
        .contact-modal h3 {
            margin-top: 0;
            font-weight: 700;
            color: #fdd405;
        }
        .contact-modal textarea {
            width: 100%;
            min-height: 150px;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #fdd405;
            border-radius: 5px;
            background-color: #1a4970; /* Slightly darker input field */
            color: #fdd405;
            resize: vertical;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 700;
            font-size: 0.9em;
            transition: background-color 0.2s;
        }
        .send-message-btn {
            background-color: #fdd405; 
            color: #215a89; 
        }
        /* Ensure text color remains the same on hover */
        .send-message-btn:hover {
            background-color: #ffea60; 
            color: #215a89; 
        }
        .cancel-modal-btn {
            background-color: #666; 
            color: #fff; 
        }
        .modal-status-message {
            margin-bottom: 15px;
            font-weight: 600;
            min-height: 1.2em; /* Reserve space */
        }
        .loading-icon {
            display: none;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #fdd405;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* --- NEW SUCCESS PROMPT STYLES --- */
        .success-prompt {
            background-color: #fdd405; /* Use the accent color for the success box */
            color: #215a89; /* Use the primary color for text */
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5);
            width: 90%;
            max-width: 400px;
            font-family: "Roboto Slab", serif;
            position: relative;
            text-align: center;
        }
        .success-prompt h4 {
            margin-top: 0;
            font-weight: 700;
            color: #215a89;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            background: none;
            border: none;
            font-size: 1.5em;
            font-weight: 700;
            color: #215a89;
            cursor: pointer;
            line-height: 1;
            padding: 0;
        }
        
        /* --- NEW DELETION STYLES --- */
        .delete-icon {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 3em; 
            cursor: pointer;
            color: #fdd405; /* Use primary color */
            transition: color 0.2s;
            line-height: 1;
            z-index: 10; 
        }
        .delete-icon:hover {
            color: #ff4d4d; /* Red on hover */
        }
        
        .delete-modal input[type="email"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #fdd405;
            border-radius: 5px;
            background-color: #1a4970; /* Same input color as contact modal */
            color: #fdd405;
            box-sizing: border-box;
        }
        .confirm-delete-btn {
            background-color: #ff4d4d; /* Red button for deletion */
            color: white; 
        }
        .confirm-delete-btn:hover {
            background-color: #e60000;
        }
        
        /* --- Tally Counter Style --- */
        .participant-tally {
            position: absolute;
            bottom: 20px; 
            left: 20px;
            font-size: 1.2em;
            font-weight: 700;
            color: #fdd405; /* Use accent color */
        }
        
        /* --- Post Count Display Style --- */
        .post-count-display {
            font-family: "Roboto Slab", serif; 
            font-size: 1.1em; 
            font-weight: 600; 
            color: #215a89; 
            margin-bottom: 20px;
            padding: 10px 20px;
            background-color: #fdd405;
            border-radius: 8px;
            text-align: center;
        }


        /* Mobile Styles (Full Width) */
        @media (max-width: 768px) {
            .user-post-item {
                width: 100%;
                min-height: 250px;
            }
        }
    </style>';

    // --- JAVASCRIPT LOGIC ---
    $output .= '<script type="text/javascript">
        jQuery(document).ready(function($) {

            // State variables for the modals
            var currentPostId = null;
            var currentDeletePostId = null;

            // Function to close the main contact modal
            function closeModal() {
                $("#contact-modal-overlay").css("display", "none");
                $("#modal-message-textarea").val(""); // Clear textarea
                $("#modal-status-message").text(""); // Clear status
                $("#send-message-btn").prop("disabled", false).text("Send Message"); // Reset button
                $("#loading-icon").css("display", "none");
            }
            
            // Function to close the new success modal
            function closeSuccessModal() {
                $("#success-prompt-overlay").css("display", "none");
            }
            
            // Function to close the new delete modal
            function closeDeleteModal() {
                $("#delete-modal-overlay").css("display", "none");
                $("#delete-modal-email").val(""); // Clear email field
                $("#delete-modal-status-message").text(""); // Clear status
                $("#confirm-delete-btn").prop("disabled", false).text("Confirm Delete"); // Reset button
                $("#delete-loading-icon").css("display", "none");
            }


            // --- AJAX Submission Functions ---

            // Function to handle the actual Team Up AJAX submission
            function submitContactForm(message) {
                if (currentPostId === null) {
                    $("#modal-status-message").text("Error: Post ID is missing.");
                    return;
                }

                $("#send-message-btn").prop("disabled", true).text("Sending..."); 
                $("#loading-icon").css("display", "inline-block");

                $.ajax({
                    url: "' . admin_url('admin-ajax.php') . '",
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: "send_team_up_email",
                        post_id: currentPostId,
                        message: message
                    },
                    success: function(response) {
                        // 1. Close the original message modal
                        closeModal(); 

                        if (response.success) {
                            // 2. Display the success prompt using the generic message
                            $("#success-prompt-message").text(response.data.message);
                            $("#success-prompt-overlay").css("display", "flex");
                            
                            // 3. Check for the auto-delete FLAG and reload
                            if (response.data.post_deleted) { // <<--- CHECKING THE NEW FLAG
                                // If post was auto-deleted, reload the page after a brief delay
                                setTimeout(function() {
                                    location.reload(); 
                                }, 2000); 
                                return;
                            }
                            
                            // 4. Manually update the tally display on success (Only if NOT deleted)
                            var $tallyElement = $(".user-post-item[data-post-id=\'" + currentPostId + "\']").find(".participant-tally");
                            // Capture the full current text, including the prefix for robustness
                            var currentText = $tallyElement.text();
                            var countMatch = currentText.match(/(\d+) \/ (\d+)/);
                            
                            if (countMatch) {
                                var currentCount = parseInt(countMatch[1], 10);
                                var preferredNumber = parseInt(countMatch[2], 10);
                                var prefix = currentText.substring(0, countMatch.index); // Capture "number of taken spots: "
                                
                                // Only update if the counter is less than the preferred number
                                if (currentCount < preferredNumber) {
                                    var newCount = currentCount + 1;
                                    $tallyElement.text(prefix + newCount + " / " + preferredNumber + ")");
                                } else if (currentCount >= preferredNumber) {
                                    console.log("Tally not updated: Group is already full.");
                                }
                            }
                            
                        } else {
                            // If AJAX succeeded but PHP returned an error
                            $("#modal-status-message").text(response.data.message).css("color", "red");
                            // Re-open the main modal briefly to show the error
                            $("#contact-modal-overlay").css("display", "flex");
                        }
                        
                        // Reset button text in case the main modal is re-opened due to error
                        $("#send-message-btn").prop("disabled", false).text("Send Message"); 
                        $("#loading-icon").css("display", "none");

                    },
                    error: function() {
                        $("#modal-status-message").text("An unexpected error occurred while contacting the server.").css("color", "red");
                        $("#send-message-btn").prop("disabled", false).text("Try Again");
                        $("#loading-icon").css("display", "none");
                    }
                });
            }
            
            // Function to handle the actual Post Deletion AJAX submission
            function submitDeleteForm(postId, email) {
                if (postId === null || email.length === 0) {
                    $("#delete-modal-status-message").text("Error: Missing required data.").css("color", "red");
                    return;
                }

                $("#confirm-delete-btn").prop("disabled", true).text("Deleting..."); 
                $("#delete-loading-icon").css("display", "inline-block");

                $.ajax({
                    url: "' . admin_url('admin-ajax.php') . '",
                    type: "POST",
                    dataType: "json",
                    data: {
                        action: "delete_user_post",
                        post_id: postId,
                        email: email
                    },
                    success: function(response) {
                        $("#delete-loading-icon").css("display", "none");

                        if (response.success) {
                            // Success: Close modal, show success prompt, and refresh the page
                            closeDeleteModal();
                            
                            // Show a quick success message using the existing prompt modal structure
                            $("#success-prompt-message").text(response.data.message + " The page will refresh shortly.");
                            $("#success-prompt-overlay").css("display", "flex");
                            
                            // Remove the deleted post element from the DOM and refresh after a delay
                            setTimeout(function() {
                                // Reload page to reflect changes
                                location.reload(); 
                            }, 2000); 

                        } else {
                            // Failure (Email Mismatch or other error)
                            $("#delete-modal-status-message").text(response.data.message).css("color", "red");
                            $("#confirm-delete-btn").prop("disabled", false).text("Try Again"); 
                        }
                    },
                    error: function() {
                        $("#delete-modal-status-message").text("An unexpected server error occurred during deletion.").css("color", "red");
                        $("#confirm-delete-btn").prop("disabled", false).text("Try Again");
                        $("#delete-loading-icon").css("display", "none");
                    }
                });
            }


            // --- Primary Click Handlers ---

            // 1. Open Contact Modal Logic
            $(".contact-poster-btn").on("click", function() {
                currentPostId = $(this).data("post-id");
                currentPostTitle = $(this).closest(".user-post-item").find(".post-title").text();

                // Update modal title
                $("#modal-post-title").text(currentPostTitle);
                
                // Show modal
                $("#contact-modal-overlay").css("display", "flex");
                $("#modal-message-textarea").focus();
            });

            // 2. Send Button Click Handler
            $("#send-message-btn").on("click", function() {
                var userMessage = $("#modal-message-textarea").val().trim();
                
                if (userMessage.length === 0) {
                    // Changed prompt to confirm() which is supported
                    if (confirm("You haven\'t typed a custom message. Are you sure you want to send an empty message?")) { 
                        submitContactForm(""); // Send empty message
                    }
                } else {
                    submitContactForm(userMessage);
                }
            });

            // 3. Cancel/Close Contact Modal Logic
            $("#cancel-modal-btn, #contact-modal-overlay").on("click", function(e) {
                // Check if the click was on the overlay itself or the cancel button
                if (e.target.id === "contact-modal-overlay" || e.target.id === "cancel-modal-btn") {
                    closeModal();
                }
            });
            // Stop clicks inside the modal from closing it
            $(".contact-modal").on("click", function(e) {
                e.stopPropagation();
            });
            
            // 4. Close Success Prompt Logic
            $("#close-success-prompt, #success-prompt-overlay, #success-prompt-overlay .close-btn").on("click", function(e) {
                // Check if the click was on the overlay itself or the close button
                if (e.target.id === "success-prompt-overlay" || $(e.target).hasClass("close-btn")) {
                    closeSuccessModal();
                }
            });
            // Stop clicks inside the prompt from closing it
            $(".success-prompt").on("click", function(e) {
                e.stopPropagation();
            });
            
            // 5. Open Delete Modal Logic
            $(".delete-icon").on("click", function(e) {
                e.stopPropagation(); // Prevents triggering any parent click handlers
                currentDeletePostId = $(this).data("post-id");
                var postTitle = $(this).data("post-title");

                // Update modal title
                $("#delete-modal-post-title").text(postTitle);
                
                // Show modal
                $("#delete-modal-overlay").css("display", "flex");
                $("#delete-modal-email").focus();
            });

            // 6. Confirm Delete Button Click Handler
            $("#confirm-delete-btn").on("click", function() {
                var userEmail = $("#delete-modal-email").val().trim();
                
                if (userEmail.length === 0) {
                    $("#delete-modal-status-message").text("Please enter your email to confirm.").css("color", "red");
                    $("#delete-modal-email").focus();
                    return;
                }
                
                // Simple email validation check
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(userEmail)) {
                    $("#delete-modal-status-message").text("Please enter a valid email format.").css("color", "red");
                    return;
                }
                
                submitDeleteForm(currentDeletePostId, userEmail);
            });

            // 7. Cancel/Close Delete Modal Logic
            $("#cancel-delete-btn, #delete-modal-overlay").on("click", function(e) {
                if (e.target.id === "delete-modal-overlay" || e.target.id === "cancel-delete-btn") {
                    closeDeleteModal();
                }
            });
            // Stop clicks inside the modal from closing it
            $(".delete-modal").on("click", function(e) {
                e.stopPropagation();
            });


            // --- Truncation/Expansion Logic ---

            // Helper function to check if an element\'s content is overflowing its container
            function isOverflown(element) {
                // Check if the scroll height (total content height) is greater than the client height (visible height)
                return element.scrollHeight > element.clientHeight;
            }
            
            // Initial check for Content Overflow and Show/Hide Button
            $(".user-post-item").each(function() {
                var $postItem = $(this);
                var container = $postItem.find(".post-content-container")[0];
                var $btn = $postItem.find(".show-more-btn");

                // If the content is overflowing (meaning it\'s truncated), show the button
                if (container && isOverflown(container)) {
                    $btn.css("display", "block"); 
                }
            });

            // Truncation/Expansion Toggle
            $(".show-more-btn").on("click", function() {
                var $btn = $(this);
                var $postItem = $btn.closest(".user-post-item");
                var $container = $postItem.find(".post-content-container");
                
                if ($container.hasClass("expanded")) {
                    // Collapse
                    $container.removeClass("expanded");
                    $btn.text("Show More");
                    
                    // Optional: Scroll to the top of the post item when collapsing
                    $(\'html, body\').animate({
                        scrollTop: $postItem.offset().top - 20
                    }, 500);
                } else {
                    // Expand
                    $container.addClass("expanded");
                    $btn.text("Show Less");
                }
            });
        });
    </script>';

    // --- Post Count Display ---
    if ($total_posts > 0) {
        $output .= '<div class="post-count-display">';
        $output .= 'Total Projects Available: <strong>' . $total_posts . '</strong><br>';
        $output .= 'Projects Matching Your Filters: <strong>' . $displayed_posts . '</strong>';
        $output .= '</div>';
    }


    if ( $posts_query->have_posts() ) {
        $output .= '<div class="user-posts-list">';
        while ( $posts_query->have_posts() ) {
            $posts_query->the_post();
            // --- STYLED HTML STRUCTURE ---
            $post_id = get_the_ID();
            $post_title_attr = esc_attr(get_the_title());
            
            // Tally Data Retrieval
            $current_count = intval(get_post_meta($post_id, 'ff_current_participants', true));
            $preferred_number = get_post_meta($post_id, 'ff_preferred_number', true);
            
            // Extract numerical value from preferred_number (e.g., "3 people" -> 3)
            $preferred_number_int = intval(preg_replace('/[^0-9]/', '', $preferred_number));
            if ($preferred_number_int < 1) {
                $preferred_number_int = 1; // Prevent division by zero and provide minimum capacity
            }
            $tally_display = '(' . $current_count . ' / ' . $preferred_number_int . ')';


            $output .= '<div class="user-post-item" data-post-id="' . $post_id . '">';
            
            // Delete Icon (Top Right)
            $output .= '<span class="delete-icon" data-post-id="' . $post_id . '" data-post-title="' . $post_title_attr . '" title="Delete This Project">&#x1F5D1;</span>';
            
            // (title) - Roboto Slab 700
            $output .= '<h2 class="post-title" style="
                font-size: 1.6em; 
                margin-top: 0; 
                margin-bottom: 10px;
                font-weight: 700;
                color: #fdd405;
            ">' . $post_title_attr . '</h2>';

            // (description) - Wrapped in .post-content-container for truncation
            $output .= '<div class="post-content-container">';
            $output .= '<div class="post-content" style="
                font-weight: 500;
                font-size: 1em;
                color: #fdd405;
            ">' . get_the_content() . '</div>';
            $output .= '</div>'; // .post-content-container

            // Show More Button (Initally hidden by CSS, shown by JS only if content overflows)
            if (get_the_content()) {
                $output .= '<button class="show-more-btn">Show More</button>';
            }
            
            // MODIFICATION 1: Added prefix before the tally
            $output .= '<span class="participant-tally">number of taken spots: ' . esc_html($tally_display) . '</span>';
            
            // This container now only holds the Contact button, ensuring it stays at the bottom-right.
            $output .= '<div style="display: flex; justify-content: flex-end; align-items: flex-end; width: 100%; margin-top: auto;">'; 
            
            // Contact Button - Bottom Right (Using the new .contact-poster-btn class defined in <style>)
            $output .= '<button class="contact-poster-btn" data-post-id="' . $post_id . '">Team Up / Contact</button>';

            $output .= '</div>'; // End of flex container for button
            $output .= '</div>'; // .user-post-item
        }

        $output .= '</div>'; // .user-posts-list
        wp_reset_postdata();

    } else {
        // --- REFINED NO POSTS MESSAGE LOGIC ---
        if ($total_posts === 0) {
            $message = 'there are no projects, be the first to post!';
        } else {
            // Posts exist, but filters excluded all of them
            $message = 'No posts match your current filtering criteria.';
        }
        
        $output .= '<p style="padding: 20px; border: 1px dashed #fdd405; border-radius: 5px; background-color: #215a89; color: #fdd405; font-family: \'Roboto Slab\', serif;">' . $message . '</p>';
    }
    
    // --- MODAL HTML STRUCTURE (outside the posts loop) ---
    // 1. Primary Contact Modal (Existing)
    $output .= '<div id="contact-modal-overlay" class="modal-overlay">';
    $output .= '<div class="contact-modal">';
    $output .= '<h3>Contact Poster for: <span id="modal-post-title"></span></h3>';
    // Updated Paragraph
    $output .= '<p>Send a message to the project poster. We will include your name and email address in the message.</p>';
    // Updated Placeholder
    $output .= '<textarea id="modal-message-textarea" placeholder="Optional: Type anything you would like the project poster to know."></textarea>';
    $output .= '<div id="modal-status-message" class="modal-status-message"></div>';
    $output .= '<div class="modal-actions">';
    $output .= '<div id="loading-icon" class="loading-icon"></div>';
    $output .= '<button id="cancel-modal-btn" class="modal-btn cancel-modal-btn">Cancel</button>';
    $output .= '<button id="send-message-btn" class="modal-btn send-message-btn">Send Message</button>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';
    
    // 2. NEW Success Prompt Modal
    $output .= '<div id="success-prompt-overlay" class="modal-overlay">';
    $output .= '<div class="success-prompt">';
    $output .= '<button id="close-success-prompt" class="close-btn">&times;</button>';
    $output .= '<h4>Success!</h4>';
    $output .= '<p id="success-prompt-message"></p>';
    $output .= '</div>';
    $output .= '</div>';
    
    // 3. NEW Delete Confirmation Modal
    $output .= '<div id="delete-modal-overlay" class="modal-overlay">';
    $output .= '<div class="contact-modal delete-modal">'; // Reusing base styles
    $output .= '<h3>Delete Project: <span id="delete-modal-post-title"></span></h3>';
    $output .= '<p>To confirm deletion, please enter the **exact email address** used to create this project.</p>';
    $output .= '<input type="email" id="delete-modal-email" placeholder="Enter your project email address" required>';
    $output .= '<div id="delete-modal-status-message" class="modal-status-message"></div>';
    $output .= '<div class="modal-actions">';
    $output .= '<div id="delete-loading-icon" class="loading-icon"></div>';
    $output .= '<button id="cancel-delete-btn" class="modal-btn cancel-modal-btn">Cancel</button>';
    $output .= '<button id="confirm-delete-btn" class="modal-btn confirm-delete-btn">Confirm Delete</button>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';


    return $output;
}
add_shortcode( 'display_user_posts', 'display_user_posts_func' );
