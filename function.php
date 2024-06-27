// Shortcode function to display the categories, search form, and main content area
function my_ajax_filter_shortcode() {
    ob_start();
    ?>
    <div id="ajax-search-form">
        <form id="search-form">
            <input type="text" id="search-input" placeholder="Search...">
        </form>
    </div>

    <div class="ajax_filter_container">
        <div id="category-sidebar">
            <ul id="category-list">
                <!-- All Posts link -->
                <li><a href="#" class="category-link main-category active" data-id="all">All Posts</a></li>
                <?php
                $categories = get_categories(['hide_empty' => 0, 'parent' => 0]);
                foreach ($categories as $category) {
                    echo '<li><a href="#" class="category-link main-category" data-id="' . $category->term_id . '">' . $category->name . '</a></li>';
                }
                ?>
            </ul>
        </div>

        <div id="main-content">
            <?php
            // Display latest posts initially
            $latest_posts = get_posts(['numberposts' => 10]);
            echo '<ul class="posts">';
            foreach ($latest_posts as $post) {
                setup_postdata($post);
                echo '<li>';
                $url = wp_get_attachment_url(get_post_thumbnail_id($post->ID), 'thumbnail');
                ?>
                <img src="<?php echo $url ?>" />
                <?php
                echo '<h3><a href="' . get_permalink($post->ID) . '">' . get_the_title($post->ID) . '</a></h3>';
                echo '</li>';
            }
            wp_reset_postdata();
            echo '</ul>';
            ?>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Handle category clicks
        $(document).on('click', '.category-link, .subcategory-link', function(e) {
            e.preventDefault();

            var categoryId = $(this).data('id');
            var linkElement = $(this);

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                dataType: 'json', // Expecting JSON response
                data: {
                    action: 'fetch_posts_by_category',
                    category_id: categoryId
                },
                success: function(response) {
                    if (response.success) {
                        // Remove active class from all categories and add to the clicked one
                        $('.category-link, .subcategory-link').removeClass('active');
                        linkElement.addClass('active');

                        // Update the main content with the new posts
                        $('#main-content').html(response.data.posts);

                        // If it's a main category click, handle subcategories
                        if (linkElement.hasClass('main-category')) {
                            // Remove existing subcategories
                            $('.subcategories').remove();
                            // Append the new subcategories if not "All Posts"
                            if (categoryId !== 'all' && response.data.subcategories.trim() !== '<ul class="subcategories"></ul>') {
                                linkElement.closest('li').append(response.data.subcategories);
                            }
                        }
                    } else {
                        alert('Error fetching data.');
                    }
                },
                error: function() {
                    alert('Error fetching data.');
                }
            });
        });

        // Handle live search input
        $('#search-input').on('input', function() {
            var searchQuery = $(this).val();

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                dataType: 'json', // Expecting JSON response
                data: {
                    action: 'search_posts',
                    query: searchQuery
                },
                success: function(response) {
                    if (response.success) {
                        // Remove active class from all categories
                        $('.category-link, .subcategory-link').removeClass('active');

                        // Update the main content with the search results
                        $('#main-content').html(response.data.posts);
                    } else {
                        alert('Error fetching data.');
                    }
                },
                error: function() {
                    alert('Error fetching data.');
                }
            });
        });
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('ajax_filter', 'my_ajax_filter_shortcode');

// Handle the AJAX request for fetching posts by category
add_action('wp_ajax_fetch_posts_by_category', 'fetch_posts_by_category');
add_action('wp_ajax_nopriv_fetch_posts_by_category', 'fetch_posts_by_category');

function fetch_posts_by_category() {
    if (!isset($_POST['category_id'])) {
        wp_send_json_error('Category ID is missing.');
    }

    $category_id = $_POST['category_id'];

    // If "All Posts" is clicked, fetch all posts
    if ($category_id == 'all') {
        $posts = get_posts(['posts_per_page' => -1]);
    } else {
        // Fetch posts by category
        $posts = get_posts(['category' => intval($category_id), 'posts_per_page' => -1]);
    }

    // Generate posts HTML
    $posts_html = '<ul class="posts">';
    foreach ($posts as $post) {
        setup_postdata($post);
        $url = wp_get_attachment_url(get_post_thumbnail_id($post->ID), 'thumbnail');
        $posts_html .= '<li><img src="' . $url . '" />';
        $posts_html .= '<h3><a href="' . get_permalink($post->ID) . '">' . get_the_title($post->ID) . '</a></h3></li>';
    }
    wp_reset_postdata();
    $posts_html .= '</ul>';

    // Fetch subcategories if not "All Posts"
    $subcategories_html = '<ul class="subcategories">';
    if ($category_id !== 'all') {
        $subcategories = get_categories(['parent' => intval($category_id), 'hide_empty' => 0]);
        foreach ($subcategories as $subcategory) {
            $subcategories_html .= '<li><a href="#" class="subcategory-link" data-id="' . $subcategory->term_id . '">' . $subcategory->name . '</a></li>';
        }
    }
    $subcategories_html .= '</ul>';

    $response = [
        'subcategories' => $subcategories_html,
        'posts' => $posts_html
    ];
    wp_send_json_success($response);
}

// Handle the AJAX request for searching posts
add_action('wp_ajax_search_posts', 'search_posts');
add_action('wp_ajax_nopriv_search_posts', 'search_posts');

function search_posts() {
    if (!isset($_POST['query'])) {
        wp_send_json_error('Search query is missing.');
    }

    $query = sanitize_text_field($_POST['query']);
    $posts = get_posts([
        's' => $query,
        'posts_per_page' => -1
    ]);

    // Generate posts HTML
    $posts_html = '<ul class="posts">';
    foreach ($posts as $post) {
        setup_postdata($post);
        $url = wp_get_attachment_url(get_post_thumbnail_id($post->ID), 'thumbnail');
        $posts_html .= '<li><img src="' . $url . '" />';
        $posts_html .= '<h3><a href="' . get_permalink($post->ID) . '">' . get_the_title($post->ID) . '</a></h3></li>';
    }
    wp_reset_postdata();
    $posts_html .= '</ul>';

    $response = [
        'posts' => $posts_html
    ];
    wp_send_json_success($response);
}
