<?php /*

**************************************************************************

Plugin Name:  Minimal Gallery
Plugin URI:   https://github.com/noknokstdio/minimal-gallery
Description:  This plugin remplace the default gallery of Wordpress by a minimalist one.
Version:      0.1.0
Author:       Javier López Úbeda
Author URI:   http://www.noknokstdio.com

**************************************************************************

Copyright (C) 2012 Javier López Úbeda

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.

**************************************************************************/

add_action('admin_menu', 'minimal_gallery_menu');

function minimal_gallery_menu() {
    add_submenu_page('options-general.php', 'minimal-gallery', 'Minimal Gallery', 'manage_options', 'minimal-gallery-menu', 'minimal_gallery_options');
}

function minimal_gallery_options() {
    ?>
<div class="wrap">
<h2>Minimal Gallery</h2>
    
<table class="form-table"><tbody>
    <tr valign="top">
        <th scope="row"><label for="width"><?php echo __('Width', "minimal-gallery") ?></label></th>
        <td><input name="width" type="text" id="width" value="500" class="regular-text">
        <span class="description">px</span></td>
    </tr>
    <tr valign="top">
        <th scope="row"><label for="height"><?php echo __('Height', "minimal-gallery") ?></label></th>
        <td><input name="height" type="text" id="height" value="500" class="regular-text">
        <span class="description">px</span></td>
    </tr>
</tbody></table>

<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Guardar cambios"></p>

</div>
<div class="clear"></div>
    <?php
}

function minimal_gallery_init() {
    wp_enqueue_script('jquery');
    $plugin_dir = basename(dirname(__FILE__));
    load_plugin_textdomain("minimal-gallery", false, $plugin_dir.'/languages/');
}
add_action('init', 'minimal_gallery_init');

remove_shortcode('gallery', 'gallery_shortcode');
add_shortcode('gallery', 'gallery_shortcode_mg');

function gallery_shortcode_mg($attr) {
    global $post;

    static $instance = 0;
    $instance++;

    $output = apply_filters('post_gallery', '', $attr);
    if ( $output != '' )
        return $output;

    if ( isset( $attr['orderby'] ) ) {
        $attr['orderby'] = sanitize_sql_orderby( $attr['orderby'] );
        if ( !$attr['orderby'] )
            unset( $attr['orderby'] );
    }

    extract(shortcode_atts(array(
        'order'      => 'ASC',
        'orderby'    => 'menu_order ID',
        'id'         => $post->ID,
        'itemtag'    => 'dl',
        'icontag'    => 'dt',
        'captiontag' => 'dd',
        'columns'    => 3,
        'size'       => 'thumbnail',
        'include'    => '',
        'exclude'    => ''
    ), $attr));

    $id = intval($id);
    if ('RAND' == $order) $orderby = 'none';

    if ( !empty($include) ) {
        $include = preg_replace( '/[^0-9,]+/', '', $include );
        $_attachments = get_posts( array('include' => $include, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );

        $attachments = array();
        foreach ( $_attachments as $key => $val ) {
            $attachments[$val->ID] = $_attachments[$key];
        }
    } elseif ( !empty($exclude) ) {
        $exclude = preg_replace( '/[^0-9,]+/', '', $exclude );
        $attachments = get_children( array('post_parent' => $id, 'exclude' => $exclude, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
    } else {
        $attachments = get_children( array('post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'attachment', 'post_mime_type' => 'image', 'order' => $order, 'orderby' => $orderby) );
    }

    if (empty($attachments)) return '';
    
    $size = array(584, 500);
    
    if (is_feed()) {
        $output = "\n";
        foreach ( $attachments as $att_id => $attachment )
            $output .= wp_get_attachment_link($att_id, $size, true) . "\n";
        return $output;
    }

    $itemtag = tag_escape($itemtag);
    $captiontag = tag_escape($captiontag);
    $float = is_rtl() ? 'right' : 'left';

    $selector = "gallery-{$instance}";

    $output .= '
        <div class="gallery-navigation">
            <span style="float: left;"><a href="javascript:void(0);" onclick="galleryPrev();" class="prev">'.__("&laquo; Previous", "minimal-gallery").'</a></span>
            <span style="text-align: center;"><span class="current">0</span>/<span class="total">0</span></span>
            <span style="float: right;"><a href="javascript:void(0);" onclick="galleryNext();" class="next">'.__("Next &raquo;", "minimal-gallery").'</a></span>
        </div>
        <div id='.$selector.' class="gallery galleryid-'.$id.'">
    ';
    
    $i = 0;
    foreach ($attachments as $id => $attachment) {
        $link = isset($attr['link']) && 'file' == $attr['link'] ? wp_get_attachment_link($id, $size, false, false) : wp_get_attachment_link($id, $size, true, false);

        $output .= "<div class='gallery-item' id='gallery-item-".$i."'>";
        $image_attributes = wp_get_attachment_image_src($id, $size, false);
        $output .= '<img src="'.$image_attributes[0].'" width="'.$image_attributes[1].'" height="'.$image_attributes[2].'">';
        
        $output .= "</div>";
        $i++;
    }

    $output .= "</div><div style='clear: both;'></div>";
    
    $output .= "
<script type='text/javascript'>
var gallery_current = 0;
var gallery_total = ".$i.";
function updateInfo() {
    jQuery('.gallery-navigation .current').html(gallery_current+1);
    jQuery('.gallery-navigation .total').html(gallery_total);
    if (gallery_total-1 == gallery_current) jQuery('.gallery-navigation .next').hide();
    else jQuery('.gallery-navigation .next').show();
    if (0 == gallery_current) jQuery('.gallery-navigation .prev').hide();
    else jQuery('.gallery-navigation .prev').show();
    if ((gallery_total-1 == gallery_current) || (0 == gallery_current)) jQuery('.gallery-navigation .sep').hide();
    else jQuery('.gallery-navigation .sep').show();
}
function galleryNext() {
    jQuery('#gallery-item-'+gallery_current).fadeOut('fast');
    gallery_current++;
    jQuery('#gallery-item-'+gallery_current).fadeIn('fast');
    updateInfo();
}
function galleryPrev() {
    jQuery('#gallery-item-'+gallery_current).fadeOut('fast');
    gallery_current--;
    jQuery('#gallery-item-'+gallery_current).fadeIn('fast');
    updateInfo();
}
jQuery(document).ready(function(){
    jQuery('#gallery-item-'+gallery_current).fadeIn('fast');
    updateInfo();
});
</script>
<style>
/* WordPress Image Gallery
--------------------------------------------- */
.gallery-navigation {
    /*font-size: 12px;*/
    text-align: center;
}
.gallery {
    margin: 0;
    padding:0;
    height: 500px;
}
.gallery .gallery-item {
    float: left;
    margin: 0;
    padding: 0;
    text-align: center;
    vertical-align: top;
    display: none;
    position: absolute;
}
.gallery img {
    border: 1px solid #cfcfcf;
}
.gallery .gallery-caption {
    margin-left: 0;
    padding: 5px 0px 8px 0px;
    font-family:'Droid Serif',  Times, serif;
    font-size: 12px;
    font-style: italic;
    line-height: 18px;
}
</style>
    ";
    
    return $output;
}