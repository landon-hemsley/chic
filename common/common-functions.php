<?php 

require_once('classes/class-common-comment-walker.php');
require_once('classes/class-enhanced-featured-image-meta-box.php');


//common functions I typically use across themes
if($GLOBALS['textdomain'] == null) $GLOBALS['textdomain'] = 'common';

if(!function_exists('common_enqueue_scripts')):
function common_enqueue_scripts(){
    wp_enqueue_style('common-style', get_template_directory_uri().'/common/css/style.css');
}
add_action('wp_enqueue_scripts', 'common_enqueue_scripts');
endif;

//intended to be used within the loop
if(!function_exists('common_get_single_meta')):
function common_get_single_meta(){
    global $multipage, $textdomain;

    $commentLink = '';
    if(comments_open()){
        //this must be registered in functions.php before you even get here.
        wp_enqueue_script('common-scroll');
        $commentLink = "<span class='separate'><a class='scrollable' data-destination='#{$textdomain}_comments_area' href='#'>" . (get_comments_number() > 0 ? get_comments_number() . _n(' comment', ' comments', get_comments_number(), $textdomain) : 'Leave a comment') . '</a></span>';
    }

    $pagesLink = '';
    if($multipage){
        $pagesLink = wp_link_pages(array(
            'before'    =>  "<span class='{$textdomain}-link-pages separate'>".__('Pages:', $textdomain),
            'after'     =>  "</span>",
            'echo'      =>  0
        ));
    }
    
    $editLink = "";
    if(current_user_can('edit_post', get_the_ID())) { 
        $editLink = sprintf('<span class="separate"><a href="%s" target="_blank">Edit</a></span>',  
            get_edit_post_link());
    }

    $return = "<div class='$textdomain-single-meta'>";


    $return .= sprintf('<p><span class="separate">%s <a href="%s" target="_blank">%s</a></span><span class="separate">%s</span>%s%s%s</p>',
        __('by', $textdomain),
        get_author_posts_url( get_the_author_meta( 'ID' ) ),
        get_the_author(),
        get_the_date('M d, Y'),
        $commentLink,
        $editLink,
        $pagesLink
    );

    $return .= '<p class='.$textdomain.'-single-meta-links>';
    
    $categories = wp_get_post_categories(get_the_ID(), array('fields' => 'all'));
   
    if($categories){
        $return .= '<span class="'.$textdomain.'-single-categories separate">'. __('Filed under', $textdomain) . ': ';
        $keys = array_keys($categories);
        $last = array_pop($keys);
        foreach($categories as $key => $cat){
            $comma = ($last != $key) ? ', ' : '';
            $url = get_category_link($cat->term_id);
            $return .= "<a href='$url'>{$cat->name}</a>$comma";
        }
        $return .= '</span>';
    }
    $tags = get_the_tags();
    if($tags){
        $return .= '<span class="'.$textdomain.'-single-tags separate">'.__('Tagged as', $textdomain) . ': ';
        $keys = array_keys($tags);
        $last = array_pop($keys);
        foreach(get_the_tags() as $key => $tag){
            $comma = ($key != $last) ? ', ' : '';
            $return .= "<a href=".get_tag_link($tag->term_id).">{$tag->name}</a>$comma ";
        } 
        $return .= "</span>";
    }
    $return .= '</p><!-- '.$textdomain.'single-single-meta-links -->';
    
    $return .= "</div><!-- $textdomain-single-meta -->";

    return $return;
}
endif;

if(!function_exists('common_slide_to_comments_js')):
function common_slide_to_comments_js(){
    if(is_single() && have_comments()){
        echo <<< EOT
            <script type="text/javascript">
                jQuery(".scrollable").on("click",function(){
                    var destination = jQuery(jQuery(this).data('destination'));
                    jQuery("html, body").animate({
                        scrollTop: destination.offset().top - 20,
                    }, 1000); 
                }); 
            </script>
EOT;
    }
}
add_action('wp_footer', 'common_slide_to_comments_js');
endif;


if(!function_exists('common_is_on_page_one')):
function common_is_on_page_one(){
    global $multipage, $page;
    return (!$multipage || $page == 1);
}
endif;


if(!function_exists('common_gallery_shortcode')):
function common_gallery_shortcode($output = '', $atts, $instance){
    global $textdomain;
    
    //put the pswp element at the footer
    add_action('wp_footer', 'common_photoswipe_element');
    
    //these scripts & styles must be registered elsewhere in the theme before you even get here.
    
    wp_enqueue_script('photoswipe');
    wp_enqueue_script('photoswipe-ui-default');
    wp_enqueue_script('photoswipe-render'); 

    wp_enqueue_style('photoswipe');
    wp_enqueue_style('photoswipe-default-skin');
    $return = $output; //as a fallback

    //documentation on the gallery shortcode ==> 
    //https://developer.wordpress.org/reference/functions/gallery_shortcode/
    /* The needful:
     * get all the specified ids
     * get all the post objects
     * frame / order them according to shortcode attributes
     * remember to maintain the styling schema that has already been written
     * make it so that when clicked, full-page image gallery actually pops up with captions
     * that people can click through if they want.
     */

    $settings = shortcode_atts( array(
        'order'      => 'ASC',
        'orderby'    => 'menu_order ID',
        'itemtag'    => 'figure',
        'icontag'    => 'div',
        'captiontag' => 'figcaption',
        'columns'    => 3,
        'size'       => 'full',
        'include'    => '',
        'exclude'    => '',
        'link'       => ''
    ), $atts, 'gallery' );

    $attachments = array();
    $query_vars = array(
            'post_status' => 'inherit', 
            'post_type' => 'attachment', 
            'post_mime_type' => 'image', 
            'order' => $settings['order'], 
            'orderby' => $settings['orderby'],  
    );

    if ( ! empty( $settings['include'] ) ) {
        $query_vars['include'] = $settings['include'];
    } elseif ( ! empty( $settings['exclude'] ) ) {
        $query_vars['exclude'] = $settings['exclude'];
    }
    $sortedIds = array_map( function($p){return $p->ID;}, get_posts($query_vars));
    

    $return = '';
    $guid = uniqid();
    $return .= <<< EOT
        <div data-guid="$guid" class="gallery $textdomain-gallery gallery-columns-{$settings['columns']} gallery-size-{$settings['size']}">
EOT;
    foreach($sortedIds as $id){
        $thumbnail = wp_get_attachment_image_src($id);
        $fullSize = wp_get_attachment_image_src($id, $settings['size']);
        $excerpt = apply_filters('the_excerpt', get_post_field('post_excerpt',$id));
        $return .= <<< EOT
            <{$settings['itemtag']} class="gallery-item">
                <{$settings['icontag']} class="gallery-icon landscape">
                    <a href="#" class="photoswipe-activate" data-src="{$fullSize[0]}" data-size="{$fullSize[1]}x{$fullSize[2]}">
                        <img src="{$thumbnail[0]}" />
                    </a>
                    <{$settings['captiontag']} class="gallery-caption">
                        $excerpt
                    </{$settings['captiontag']}>
                </{$settings['icontag']}>    
            </{$settings['itemtag']}>
EOT;

    }
    $return .= "</div>";

    return $return;
}
add_filter('post_gallery', 'common_gallery_shortcode', 10, 3);
endif;

if(!function_exists('common_photoswipe_element')):
function common_photoswipe_element(){
    echo <<< EOT
        <div class="pswp" id="photoswipe" tabindex="-1" role="dialog" aria-hidden="true">

            <div class="pswp__bg"></div>

            <div class="pswp__scroll-wrap">

                <div class="pswp__container">
                    <div class="pswp__item"></div>
                    <div class="pswp__item"></div>
                    <div class="pswp__item"></div>
                </div>

                <div class="pswp__ui pswp__ui--hidden">

                    <div class="pswp__top-bar">

                        <!--  Controls are self-explanatory. Order can be changed. -->

                        <div class="pswp__counter"></div>

                        <button class="pswp__button pswp__button--close" title="Close (Esc)"></button>

                        <button class="pswp__button pswp__button--share" title="Share"></button>

                        <button class="pswp__button pswp__button--fs" title="Toggle fullscreen"></button>

                        <button class="pswp__button pswp__button--zoom" title="Zoom in/out"></button>

                        <!-- Preloader demo http://codepen.io/dimsemenov/pen/yyBWoR -->
                        <!-- element will get class pswp__preloader--active when preloader is running -->
                        <div class="pswp__preloader">
                            <div class="pswp__preloader__icn">
                              <div class="pswp__preloader__cut">
                                <div class="pswp__preloader__donut"></div>
                              </div>
                            </div>
                        </div>
                    </div>

                    <div class="pswp__share-modal pswp__share-modal--hidden pswp__single-tap">
                        <div class="pswp__share-tooltip"></div> 
                    </div>

                    <button class="pswp__button pswp__button--arrow--left" title="Previous (arrow left)">
                    </button>

                    <button class="pswp__button pswp__button--arrow--right" title="Next (arrow right)">
                    </button>

                    <div class="pswp__caption">
                        <div class="pswp__caption__center"></div>
                    </div>

                </div>

            </div>

        </div>
EOT;
}
endif;


if(!function_exists('common_ifrm_oembed_filter')):
function common_ifrm_oembed_filter($cachedHtml, $url, $attr, $post_ID){
    //regex on html to see if there's an iframe
    //if there's an iframe, wrap the html in a div such that it can be presented
    //return it
    if(1 == preg_match('/^<iframe[^>]*>.*<\/iframe>/', $cachedHtml)){
        $cachedHtml = '<div class="iframe-responsive">'.$cachedHtml.'</div>';
    }
    return $cachedHtml;
}
add_filter("embed_oembed_html", 'common_ifrm_oembed_filter', 10, 4);
endif;

if(!function_exists('common_comments')):
function common_comments(){
    global $textdomain;

    ob_start();

    if(post_password_required()) return;
    
    echo '<div id="'.$textdomain.'_comments_area">';

    if(have_comments()){
        echo sprintf('<h3 class="%s-comments-title">%s</h3>', $textdomain, __('Comments', $textdomain));
    }

    wp_list_comments(array(
        'walker' => new Common_Comment_Walker($textdomain),
        'style' => 'div',
        'format' => 'html5',
        'avatar_size' => 40,
        'max_depth' => '3',
    ));

    $comments_links = paginate_comments_links(array(
        'type'          => 'array',
        'add_fragment'  => '#'.$textdomain.'-comments-area',
        'prev_text'     => '&larr;',
        'next_text'     => '&rarr;',
        'echo'          => false
    ));

    if($comments_links){
        echo "<ul class='$textdomain-comments-pagination page-numbers'>\r\n";
        foreach($comments_links as $link){
            echo "<li class='$textdomain-comments-page-link'>$link</li>\r\n";
        }
        echo "</ul><!-- $textdomain-comments-pagination -->\r\n";
    }

    $commenter = wp_get_current_commenter();
    $req = get_option('require_name_email');

    comment_form(array(
        'fields' => array(
            'author' => 
                '<div class="'.$textdomain.'-comment-respond-section">
                    <label for="'.$textdomain.'-comment-form-author">'.__('Your name ', $textdomain).($req ? '<span class="'.$textdomain.'-comment-form-required">*</span>' : '' ).'</label>
                    <input id="'.$textdomain.'-comment-form-author" class="input-text" name="author" type="text" value="'. esc_attr($commenter['comment_author']) .'" />
                </div>', 
            'email' => 
                '<div class="'.$textdomain.'-comment-respond-section">
                    <label for="'.$textdomain.'-comment-form-email">'.__('Your email ', $textdomain).($req ? '<span class="'.$textdomain.'-comment-form-required">*</span>':'') .'</label>
                    <input id="'.$textdomain.'-comment-form-email" class="input-text" name="email" value="'. esc_attr($commenter['comment_author_email']) .'" />
                </div>', 
        ),
        'comment_notes_before' => '<p>'.__('Your email address will not be published. Required fields are marked',$textdomain).'<span class="'.$textdomain.'-comment-form-required">*</span></p>',
        'comment_field' => '<div class="'.$textdomain.'-comment-respond-section"><label for="'.$textdomain.'-comment">'.__('Comment text ',$textdomain) . ($req ? '<span class="'.$textdomain.'-comment-form-required">*</span>':'') .'</label><textarea id="'.$textdomain.'_comment_respond" class="input-text" name="comment"></textarea></div>',
        'class_form' => $textdomain.'-comment-form',
        'submit_field' => '<button type="submit" class="'.$textdomain.'-comment-submit button">'.__('Submit',$textdomain).'</button>%2$s',
        'title_reply' => 'Leave a comment',
        'format' => 'html5'

    ));
    
    echo '</div>';

    return ob_get_clean();
}
    
endif;

if(!function_exists('common_get_feed_image_url')):
function common_get_feed_image_url($size = 'thumbnail'){
    global $post;
    //if has defined thumbnail, return url of thumbnail
    if(has_post_thumbnail($post)) return get_the_post_thumbnail_url($post, $size);
    else{
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(apply_filters('the_content',$post->post_content));
        libxml_clear_errors();
        $images = $dom->getElementsByTagName('img');
        if($images->length >= 1){
            //else if content has img tag, return url of image
            return $images->item(0)->getAttribute('src');
        }
        else{
            //else if content has youtube video, return url of youtube preview img
            $iframes = $dom->getElementsByTagName('iframe');
            foreach($iframes as $iframe){
                $src = $iframe->getAttribute('src');
                if(preg_match('/youtube\.com\/embed\/(.+)/', $src, $matches)){
                    return "https://img.youtube.com/vi/{$matches[1]}/0.jpg";
                }
            }
            //else return default image, based on post type
            $filename = get_post_format($post->ID) ? 'feed-'.get_post_format($post->ID) : 'feed-default';
            return get_template_directory_uri()."/assets/img/$filename.png";
        }
    }
}
endif;

//#region getting a link for link type posts out of the content
if(!function_exists('common_first_link_in_link_posts')):
function common_first_link_in_link_posts($url){
    global $post;
    if(get_post_format($post->ID) !== 'link') return $url;
    $candidate = common_parse_content_for_link(apply_filters('the_content', $post->post_content));
    return empty($candidate) ? $url : $candidate;
}
add_filter('the_permalink', 'common_first_link_in_link_posts');
endif;

if(!function_exists('common_parse_content_for_link')):
function common_parse_content_for_link($content_to_parse){
    $dom = new DOMDocument;
    $dom->loadHTML($content_to_parse);
    $anchors = $dom->getElementsByTagName('a');
    if($anchors->length <= 0) return '';
    return $anchors->item(0)->getAttribute('href');
}
endif;


//#endregion getting a link for link type post out of the content

//get previous/next post links
//to be used in the loop
if(!function_exists('common_link_posts')):
function common_link_posts(){
    global $textdomain;
    $prev_post_link = get_previous_post_link('<div class="common-post-link common-prev-post-link"><span class="common-post-link-arrow">&larr; </span><span class="common-post-link-label">Previously: </span>%link</div>');
    $next_post_link = get_next_post_link('<div class="common-post-link common-next-post-link"><span class="common-post-link-label">Next: </span>%link<span class="common-post-link-arrow"> &rarr;</div>');
    
    if(!empty($prev_post_link) || !empty($next_post_link)){
        echo '<hr style="clear:both;"/>';
        printf('<h3 class="common-post-link-read-more">%s</h3>', __('Read more', $textdomain));
        printf('<div class="%1$s">%2$s %3$s</div>',
            'common-post-links',
            $prev_post_link,
            $next_post_link
        );
    }
}
endif;




?>
