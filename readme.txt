=== Sectionize ===
Contributors: westonruter
Tags: HTML5, sections, toc, table of contents, seo
Tested up to: 2.9
Requires at least: 2.8
Stable tag: trunk

Parses HTML for sections demarcated by heading elements, wraps them in HTML5  
section elements, and generates table of contents with links to each.

== Description ==

<em>This plugin is developed at
<a href="http://www.shepherd-interactive.com/" title="Shepherd Interactive specializes in web design and development in Portland, Oregon">Shepherd Interactive</a>
for the benefit of the community. <b>No support is available. Please post any questions to the <a href="http://wordpress.org/tags/sectionize?forum_id=10">support forum</a>.</b></em>

Takes HTML content which contains flat heading elements inline with paragraphs
and automatically nests them withing HTML5 `<section>` elements. It also
conditionally prepends an `<ol>` Table of Contents (TOC) with links to the
sections in the content, for example:

	<nav class='toc'><ol>
		<li><a href="#section-first-top">First Top</a></li>
		<li><a href="#section-second-top">Second Top</a>
		<ol>
			<li><a href="#section-first-sub">First Sub</a></li>
			<li><a href="#section-second-sub">Second Sub</a></li>
		</ol>
		</li>
		<li><a href="#section-third-top">Third Top</a></li>
	</ol></nav>

This would reflect original post content such as:

	<h2>First Top</h2>
	Lorem ipsum dolor sit amet, consectetur adipiscing elit.
	<h2>Second Top</h2>
	Lorem ipsum dolor sit amet, consectetur adipiscing elit.
	<h3>First Sub</h2>
	Lorem ipsum dolor sit amet, consectetur adipiscing elit.
	<h3>Second Sub</h2>
	Lorem ipsum dolor sit amet, consectetur adipiscing elit.
	<h2>Third Top</h2>
	Lorem ipsum dolor sit amet, consectetur adipiscing elit.

Original post content such as this would be sectionized as follows:

	<section id="section-first-top">
		<h2>First Top</h2>
		Lorem ipsum dolor sit amet, consectetur adipiscing elit.
	</section>
	<section id="section-second-top">
		<h2>Second Top</h2>
		Lorem ipsum dolor sit amet, consectetur adipiscing elit.
		<section id="section-first-sub">
			<h3>First Sub</h2>
			Lorem ipsum dolor sit amet, consectetur adipiscing elit.
		</section>
		<section id="section-second-sub">
			<h3>Second Sub</h2>
			Lorem ipsum dolor sit amet, consectetur adipiscing elit.
		</section>
	</section>
	<section id="section-third-top">
		<h2>Third Top</h2>
		Lorem ipsum dolor sit amet, consectetur adipiscing elit.
	</section>

Adding a table of contents as such not only aids navigation for visitors once
on the page, but Google also now provides direct links to such sections in relevant search result
snippets (see <a href="http://googleblog.blogspot.com/2009/09/jump-to-information-you-want-right-from.html" title="Jump to the information you want right from the search snippets">announcement</a>).
So people browsing search results may be able to see your section links
right from the results page and then be able to jump directly to the relevant
section.

The start/end tags for both the sections and the TOC, as well as the prefixes
used when generating the section IDs, may all be configured via the following
WordPress options (with their defaults):

 * `sectionize_id_prefix`: `'section-'`
 * `sectionize_start_section`: `'<section id="%id">'`
 * `sectionize_end_section`:  `</section>`
 * `sectionize_include_toc_threshold`: `2`
 * `sectionize_before_toc`: `'<nav class="toc">'`
 * `sectionize_after_toc`: `'</nav>'`
 * `sectionize_disabled`: `false` (no corresponding function argument)

These global WordPress options may be overridden by individual posts/pages by
creating custom fields (postmeta) with the same names.

These options are retreived if their corresponding arguments are not supplied
to the `sectionize()` function (that is, if they are `null`):

	function sectionize($original_content,
	                    $id_prefix = null,
						$start_section = null,
						$end_section = null,
						$include_toc_threshold = null,
						$before_toc = null,
						$after_toc = null)

This `sectionize()` function is added as a filter for `the_content` (this is
disabled if the option or postmeta `sectionize_disabled` evaluates to `true`):

	add_filter('the_content', 'sectionize');

Noted above, the TOC is conditionally included. It is not included if:

1. there are no headings in the content (thus there is nothing to sectionize),
1. the headings are not nested properly (see below), or
1. the heading count does not meet the threshold (or the threshold is -1)

If the number of headings in the content is less than the
`include_toc_threshold` option/argument then the TOC is not displayed;
likewise, if `include_toc_threshold` is `-1` then the TOC is not displayed.

*Important!* Regarding headings being "nested properly", you must ensure that
you properly arrange your headings in a hierarchical manner in which no heading
is immediately preceeded by another heading that is more than one level greater
(e.g. an `h3` must be preceeded by an `h2` or another `h3`). For example, this
works:

	h2
		h3
		h3
			h4
		h3
	h2

But this does not:

	h2
		h4 -- fail
			h6 -- fail
	h2

If you make such a mistake, this plugin will abort and have no effect. An error
notice will be included in the HTML output in the form of an HTML comment.

Please see source code for additional documentation: numerous filters are provided
to further customize the behavior. _Be one with the code!_
To help serve HTML5 content, see the <a href="http://wordpress.org/extend/plugins/xhtml5-support/">XHTML5 Support</a<> plugin.

== Changelog ==

= 2009-11-03: 1.0 =
* Initial release

= 2009-12-17: 1.1 =
* Moved `add_option('sectionize_...)` calls to activation hook.
* Adding `sectionize_toc_text` filter so that the link text in the TOC can be customized,
  along with a default filter which strips off tags and a trailing ':'