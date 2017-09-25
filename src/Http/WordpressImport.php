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
			$dc = $item->children('excerpt', true);
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
						"body"			=> trim((string)$content->encoded, '" \n'),
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
						"body"			=> trim((string)$content->encoded, '" \n'),
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