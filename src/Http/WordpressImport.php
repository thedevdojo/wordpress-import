<?php

namespace WordpressImport\Http;

//
// WordpressImport Class Built for LaravelVoyager
// 

class WordpressImport
{
	// store the Wordpress XML
	public $wpXML;

	public $authors;
	public $attachments;
	public $categories;
	public $posts;
	public $pages;

	public $copyImages = true;
	public $secondsBeforeTimeout = 900;

	function __construct($wpXML, $copyImages, $secondsBeforeTimeout = 900)
	{
		set_time_limit($this->secondsBeforeTimeout);
		ini_set('max_execution_time', $this->secondsBeforeTimeout);
		ini_set('default_socket_timeout', $this->secondsBeforeTimeout);
		$this->wpXML = simplexml_load_file($wpXML, 'SimpleXMLElement', LIBXML_NOCDATA);

		$this->copyImages = $copyImages;
		$this->userDefaultPassword = 'password';

		$this->$secondsBeforeTimeout = $secondsBeforeTimeout;

		$this->saveAuthors();
		$this->saveCategories();
		$this->saveAttachments();
		$this->savePostsAndPages('post');
		$this->savePostsAndPages('page');
	}

	// Create new users and load them into array
	private function saveAuthors(){
		
		$wpData = $this->wpXML->channel->children('wp', true);
		$defaultUserRoleId = \TCG\Voyager\Models\Role::where('name', '=', 'user')->first()->id;
		
		foreach($wpData->author as $author){
			$this->authors[(string)$author->author_login] = array(
					'role_id' => $defaultUserRoleId,
					'name' => (string)$author->author_display_name,
					'email' => (string)$author->author_email,
					'password' => \Hash::make($this->userDefaultPassword),
				);

			$new_user = \TCG\Voyager\Models\User::create($this->authors[(string)$author->author_login]);

			// store the new id in the array
			$this->authors[(string)$author->author_login]['id'] = $new_user->id;
		}
	}

	// Create new categories and store them in the array
	private function saveCategories(){
		
		$wpData = $this->wpXML->channel->children('wp', true);

		$order = 1;
		foreach($wpData->category as $category){

			$this->categories[(string)$category->category_nicename] = array(
					'parent_id' => NULL,
					'order' => $order,
					'name' => (string)$category->cat_name,
					'slug' => (string)$category->category_nicename
				);

			$new_cat = \TCG\Voyager\Models\Category::create($this->categories[(string)$category->category_nicename]);

			$this->categories[(string)$category->category_nicename]['parent'] = (string)$category->category_parent;
			$this->categories[(string)$category->category_nicename]['id'] = $new_cat->id;

			$order += 1;
		}

		// Save any parent categories to their children
		foreach($this->categories as $category){
			if(!empty($category['parent'])){
				$parent = \TCG\Voyager\Models\Category::where('slug', '=', $category['parent'])->first();
				if(isset($parent->id)){
					$category['parent_id'] = $parent->id;
					$this_cat = \TCG\Voyager\Models\Category::find($category['id']);
					if(isset($this_cat->id)){
						$this_cat->parent_id = $parent->id;
						$this_cat->save();
					}
				}
			}
		}
	}

	// Save all the attachments in an array
	private function saveAttachments(){

		foreach($this->wpXML->channel->item as $item)
		{
			// Save The Attachments in an array
			$wpData = $item->children('wp', true);
			if($wpData->post_type == 'attachment'){
				$this->attachments[(string)$wpData->post_parent] = (string)$wpData->attachment_url;
			}

		}
	}

	private function savePostsAndPages($type = 'post')
	{
		foreach($this->wpXML->channel->item as $item)
		{

			$wpData = $item->children('wp', true);
			$content = $item->children('content', true);
			$excerpt = $item->children('excerpt', true);
			$category = NULL;
			$image = isset($this->attachments[(string)$wpData->post_id]) ? $this->attachments[(string)$wpData->post_id] : '';
			$dc = $item->children('dc', true);
			$author = NULL;
			$slug = (string)$wpData->post_name;

			if(isset($dc->creator)){
				$author = (string)$dc->creator;
			}

			if(isset($item->category["nicename"])){
				$category = (string)$item->category["nicename"];
			}

			if($type == 'post')
			{
				$status = 'PUBLISHED';
				if(isset($wpData->status) && $wpData->status != 'publish'){
					$status = 'DRAFT';
				}
				if(empty($slug)){
					$slug = 'post-' . (string)$wpData->post_id;
				}
			} 
			elseif ($type == 'page')
			{
				$status = 'ACTIVE';
				if(isset($wpData->status) && $wpData->status != 'publish'){
					$status = 'INACTIVE';
				}
				if(empty($slug)){
					$slug = 'page-' . (string)$wpData->post_id;
				}
			}



			if($wpData->post_type == $type)
			{

				if($type == 'post')
				{

					$this->posts[] = array(
						"author_id"		=> isset($this->authors[$author]['id']) ? $this->authors[$author]['id'] : 1,
						"category_id"	=> isset($this->categories[$category]['id']) ? $this->categories[$category]['id'] : NULL,
						"title"			=> trim((string)$item->title, '"'),
						"seo_title"		=> trim((string)$item->title, '"'),
						"excerpt"		=> trim((string)$excerpt->encoded, '" \n'),
						"body"			=> $this->autop(trim((string)$content->encoded, '" \n')),
						"image"			=> $this->getImage($image),
						"slug"			=> $slug,
						"status"		=> $status,
						"featured"		=> 0,
						"created_at"	=> \Carbon\Carbon::parse((string)$wpData->post_date),
						"updated_at"	=> \Carbon\Carbon::parse((string)$wpData->post_date),
					);

				} 
				elseif ($type == 'page')
				{

					$this->pages[] = array(
						"author_id"		=> isset($this->authors[$author]['id']) ? $this->authors[$author]['id'] : 1,
						"title"			=> trim((string)$item->title, '"'),
						"excerpt"		=> trim((string)$excerpt->encoded, '" \n'),
						"body"			=> $this->autop(trim((string)$content->encoded, '" \n')),
						"image"			=> $this->getImage($image),
						"slug"			=> $slug,
						"status"		=> $status,
						"created_at"	=> \Carbon\Carbon::parse((string)$item->pubDate),
						"updated_at"	=> \Carbon\Carbon::parse((string)$item->pubDate),
					);
				
				}

			}

		}


		if($type == 'post'){
			\TCG\Voyager\Models\Post::insert($this->posts);
		} elseif ($type == 'page'){
			\TCG\Voyager\Models\Page::insert($this->pages);
		}

	}

	/**
	 * Replaces double line-breaks with paragraph elements.
	 *
	 * A group of regex replaces used to identify text formatted with newlines and
	 * replace double line-breaks with HTML paragraph tags. The remaining
	 * line-breaks after conversion become <<br />> tags, unless $br is set to '0'
	 * or 'false'.
	 *
	 * https://gist.github.com/joshhartman/5381116
	 *
	 * @param string $pee The text which has to be formatted.
	 * @param bool $br Optional. If set, this will convert all remaining line-breaks after paragraphing. Default true.
	 * @return string Text which has been converted into correct paragraph tags.
	 */
	private function autop($pee, $br = true) {
	  $pre_tags = array();

		if ( trim($pee) === '' )
			return '';

		$pee = $pee . "\n"; // just to make things a little easier, pad the end

		if ( strpos($pee, '<pre') !== false ) {
			$pee_parts = explode( '</pre>', $pee );
			$last_pee = array_pop($pee_parts);
			$pee = '';
			$i = 0;

			foreach ( $pee_parts as $pee_part ) {
				$start = strpos($pee_part, '<pre');

				// Malformed html?
				if ( $start === false ) {
					$pee .= $pee_part;
					continue;
				}

				$name = "<pre wp-pre-tag-$i></pre>";
				$pre_tags[$name] = substr( $pee_part, $start ) . '</pre>';

				$pee .= substr( $pee_part, 0, $start ) . $name;
				$i++;
			}

			$pee .= $last_pee;
		}

		$pee = preg_replace('|<br />\s*<br />|', "\n\n", $pee);
		// Space things out a little
		$allblocks = '(?:table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|option|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|noscript|samp|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary)';
		$pee = preg_replace('!(<' . $allblocks . '[^>]*>)!', "\n$1", $pee);
		$pee = preg_replace('!(</' . $allblocks . '>)!', "$1\n\n", $pee);
		$pee = str_replace(array("\r\n", "\r"), "\n", $pee); // cross-platform newlines
		if ( strpos($pee, '<object') !== false ) {
			$pee = preg_replace('|\s*<param([^>]*)>\s*|', "<param$1>", $pee); // no pee inside object/embed
			$pee = preg_replace('|\s*</embed>\s*|', '</embed>', $pee);
		}
		$pee = preg_replace("/\n\n+/", "\n\n", $pee); // take care of duplicates
		// make paragraphs, including one at the end
		$pees = preg_split('/\n\s*\n/', $pee, -1, PREG_SPLIT_NO_EMPTY);
		$pee = '';
		foreach ( $pees as $tinkle )
			$pee .= '<p>' . trim($tinkle, "\n") . "</p>\n";
		$pee = preg_replace('|<p>\s*</p>|', '', $pee); // under certain strange conditions it could create a P of entirely whitespace
		$pee = preg_replace('!<p>([^<]+)</(div|address|form)>!', "<p>$1</p></$2>", $pee);
		$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee); // don't pee all over a tag
		$pee = preg_replace("|<p>(<li.+?)</p>|", "$1", $pee); // problem with nested lists
		$pee = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $pee);
		$pee = str_replace('</blockquote></p>', '</p></blockquote>', $pee);
		$pee = preg_replace('!<p>\s*(</?' . $allblocks . '[^>]*>)!', "$1", $pee);
		$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*</p>!', "$1", $pee);
		if ( $br ) {
			$pee = preg_replace_callback('/<(script|style).*?<\/\\1>/s', function($matches){return str_replace("\n", "<PreserveNewline />", $matches[0]);}, $pee);
			$pee = preg_replace('|(?<!<br />)\s*\n|', "<br />\n", $pee); // optionally make line breaks
			$pee = str_replace('<PreserveNewline />', "\n", $pee);
		}
		$pee = preg_replace('!(</?' . $allblocks . '[^>]*>)\s*<br />!', "$1", $pee);
		$pee = preg_replace('!<br />(\s*</?(?:p|li|div|dl|dd|dt|th|pre|td|ul|ol)[^>]*>)!', '$1', $pee);
		$pee = preg_replace( "|\n</p>$|", '</p>', $pee );

		if ( !empty($pre_tags) )
			$pee = str_replace(array_keys($pre_tags), array_values($pre_tags), $pee);

		return $pee;
	}

	private function getImage($image){

		if(!empty($image) && $this->copyImages){

			$resize_width = 1800;
	        $resize_height = null;
			$path = 'posts/'.date('FY').'/';
			$filename = basename($image);

			$img = \Image::make($image)->resize($resize_width, $resize_height,
	            function (\Intervention\Image\Constraint $constraint) {
	                $constraint->aspectRatio();
	                $constraint->upsize();
	            })->encode(pathinfo($image, PATHINFO_EXTENSION), 75);
			\Storage::disk(config('voyager.storage.disk'))->put($path.$filename, (string) $img, 'public');
		
			$image = $path.$filename;

		}

		return $image;
		

	}

}