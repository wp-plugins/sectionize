<?php
/*
Plugin Name: Sectionize
Plugin URI: http://wordpress.org/extend/plugins/sectionize/
Description: Parses HTML content for sections demarcated by heading elements: wraps HTML5 <code>section<code> elements around them, and generates table of contents with links to each section. <em>Plugin developed at <a href="http://www.shepherd-interactive.com/" title="Shepherd Interactive specializes in web design and development in Portland, Oregon">Shepherd Interactive</a>.</em>
Version: 1.1
Author: Weston Ruter
Author URI: http://weston.ruter.net/
Copyright: 2009, Weston Ruter, Shepherd Interactive <http://shepherd-interactive.com/>. GPL license.

GNU General Public License, Free Software Foundation <http://creativecommons.org/licenses/GPL/2.0/>
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * Plugin activation
 */
function sectionize_activate(){
	add_option('sectionize_id_prefix', 'section-'); //§
	add_option('sectionize_start_section', '<section id="%id">');
	add_option('sectionize_end_section',  '</section>');
	add_option('sectionize_include_toc_threshold', 2); //-1 means never include TOC
	add_option('sectionize_before_toc', '<nav class="toc">');
	add_option('sectionize_after_toc',  '</nav>');
	add_option('sectionize_disabled',  false);
}
register_activation_hook(__FILE__, "sectionize_activate");


/**
 * Takes HTML content which contains flat heading elements and automatically
 * nests them withing HTML5 <section> elements. It also prepends an <ol> Table
 * of Contents with links to the sections in the content. Nothing happens if
 * (1) there are no headings in the content,
 * (2) the headings are not nested properly, or
 * (3) the heading count does not meet the threshold (or the threshold is -1)
 *
 * @param string $original_content The HTML content to be sectionized
 * @param string $id_prefix The HTML content to be sectionized
 * @param mixed $start_section   This content is prepended to a section. If null, then the 'sectionize_start_section' option value is used. The %id is replaced with the ID generated for the section. Default: '<section id="%id">'
 * @param mixed $end_section    This content is appended to a section. If null, then the 'sectionize_end_section' option value is used. Default: '</section>'
 * @param int   $include_toc_threshold    If -1, TOC never included; otherwise, must be equal-to or less-than the number of headings in the document before the TOC is included.
 * @param mixed $before_toc   This content is prepended to the TOC. If null, then the 'sectionize_before_toc' option value is used. Default: '<nav class="toc">'
 * @param mixed $after_toc    This content is appended to the TOC. If null, then the 'sectionize_after_toc' option value is used. Default: '</nav>'
 * 
 * @return string The sectionized $original_content with an optional prepended TOC
 */
function sectionize($original_content,
					$id_prefix = null,
					$start_section = null,
					$end_section = null,
					$include_toc_threshold = null,
					$before_toc = null,
					$after_toc = null)
{
	//Return immediately if sectionize is disabled for this post
	if(_sectionize_get_postmeta_or_option('sectionize_disabled'))
		return $original_content;
	
	//Verify that the content actually has headings and gather them up
	if(!preg_match_all('{<h([1-6])\b.*?>(.*?)</h\1\s*>}si', $original_content, $headingMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) # | PREG_OFFSET_CAPTURE
		return $original_content;
	
	//Prepended to the sanitized title of a heading element
	if(is_null($id_prefix))
		$id_prefix = _sectionize_get_postmeta_or_option('sectionize_id_prefix');
	
	//The markup inserted before and after the sections
	if(is_null($start_section))
		$start_section = _sectionize_get_postmeta_or_option('sectionize_start_section');
	if(is_null($end_section))
		$end_section = _sectionize_get_postmeta_or_option('sectionize_end_section');
	$end_section_len = strlen($end_section);
	
	//Whether to include the TOC, and what comes before and after it
	if(is_null($include_toc_threshold))
		$include_toc_threshold = _sectionize_get_postmeta_or_option('sectionize_include_toc_threshold');
	
	//Determine whether or not the TOC should be included: -1 threshold means
	//  never include TOC, and any positive integer means the minimum number of
	//  headings that must be in the content before the TOC will get prepended.
	$include_toc = ($include_toc_threshold >= 0 && $include_toc_threshold <= count($headingMatches));
	if($include_toc){
		if(is_null($before_toc))
			$before_toc = _sectionize_get_postmeta_or_option('sectionize_before_toc');
		if(is_null($after_toc))
			$after_toc = _sectionize_get_postmeta_or_option('sectionize_after_toc');
	}

	//Get TOC and updated content ready
	$level = 0; //current indentation level
	$toc = '';
	if($include_toc)
		$toc = $before_toc . apply_filters('sectionize_start_toc_list', "<ol>", $level);
	$content = $original_content;
	$offset = 0;
	
	//Keep track of the IDs used so that we don't collide
	$usedIDs = array();
	for($i = 0; $i < count($headingMatches); $i++){
		
		// Generate a unique ID for the section
		$headingText = html_entity_decode(strip_tags($headingMatches[$i][2][0]), ENT_QUOTES, get_option('blog_charset'));
		$sanitizedTitle = sanitize_title_with_dashes($headingText);
		$id = apply_filters('sectionize_id', $id_prefix . $sanitizedTitle, $id_prefix, $headingText);
		if(isset($usedIDs[$id])){
			$count = 0;
			do {
				$count++;
				$id2 = $id . '-' . $count;
			}
			while(isset($usedIDs[$id2]));
			$id = $id2;
			unset($id2);
		}
		$usedIDs[$id] = true;
		
		if($i){
			$levelDiff = (int)$headingMatches[$i][1][0] - (int)$headingMatches[$i-1][1][0];
		
			// This level is greater (deeper)
			if($levelDiff == 1){
				if($include_toc)
					$toc .= apply_filters('sectionize_start_toc_list', "<ol>", $level);
				$level++;
			}
			// This level is lesser (shallower)
			else if($levelDiff < 0){
				$level += $levelDiff;
				//Error
				if($level < 0)
					return "\n<!-- It appears you started with a heading that is larger than one following it. -->\n" . $original_content;
				
				//End Section
				$content = substr_replace($content, $end_section, $headingMatches[$i][0][1]+$offset, 0);
				$offset += $end_section_len;
				
				if($include_toc)
					$toc .= '</li>';
				while($levelDiff < 0){
					if($include_toc)
						$toc .= apply_filters('sectionize_end_toc_list', "</ol>")
						      . apply_filters('sectionize_end_toc_item', '</li>');
					
					//End Section
					$content = substr_replace($content, $end_section, $headingMatches[$i][0][1]+$offset, 0);
					$offset += $end_section_len;
					
					$levelDiff++;
				}
			}
			//Heading is the same as the previous
			else if($levelDiff == 0) {
				if($include_toc)
					$toc .= apply_filters('sectionize_end_toc_item', '</li>');
				
				//End Section
				$content = substr_replace($content, $end_section, $headingMatches[$i][0][1]+$offset, 0);
				$offset += $end_section_len;
			}
			//Error!
			else { //($levelDiff > 1)
				return "\n<!-- Headings must only be incremented one at a time! You went from <h" . $headingMatches[$i-1][1][0] . "> to <h" . $headingMatches[$i][1][0] . "> -->\n" . $original_content;
			}
		}
		
		if($include_toc){
			//Open new item
			$toc .= apply_filters('sectionize_start_toc_item', '<li>', $level);
			
			// Link to the section
			$toc .= apply_filters('sectionize_toc_link', (
				"<a href='#" . esc_attr($id) . "'>" .
				apply_filters('sectionize_toc_text', $headingMatches[$i][2][0], $level) .
				"</a>")
			, $level);
		}
		
		//Start new section
		$_start_section = apply_filters('sectionize_start_section', str_replace('%id', esc_attr($id), $start_section));
		$content = substr_replace($content, $_start_section, $headingMatches[$i][0][1]+$offset, 0);
		$offset += strlen($_start_section);
	}
	while($level >= 0){
		if($include_toc)
			$toc .= apply_filters('sectionize_end_toc_item', '</li>')
			      . apply_filters('sectionize_end_toc_list', "</ol>");
		$level--;
		
		//End Section
		$content .= $end_section;
	}
	
	return $include_toc ?
		$toc . $after_toc . $content :
		$content;
}
add_filter('the_content', 'sectionize');


/**
 * Helper which returns postmeta[$name] if it exists, otherwise get the option
 */
function _sectionize_get_postmeta_or_option($name){
	global $post;
	if(!empty($post) && $post->ID){
		global $wp_query;
		$value = get_post_meta($post->ID, $name, true);
		if($value !== '')
			return $value;
	}
	return get_option($name);
}

/**
 * Default sectionized TOC item link text filter which does strip_tags
 * and trims colon off of end.
 */
function sectionize_toc_text_default_filter($text){
	return trim(rtrim(strip_tags($text), ':'));
}
add_filter('sectionize_toc_text', 'sectionize_toc_text_default_filter');
