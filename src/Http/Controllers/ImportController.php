<?php

namespace WordpressImport\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Storage;
use WordpressImport\Http\WordpressImport;

class ImportController extends BaseController
{


	public function import(Request $request){

		$copyImages = false;
		if($request->copyimages){
			$copyImages = true;
		}

		$timeout = 900;
		if($request->timeout){
			$timeout = $request->timeout;
		}

		if($request->hasFile('wpexport')){

			$mimeType = $request->file('wpexport')->getMimeType();

			if($mimeType == "text/xml" || $mimeType == "application/xml"){

				$dir = 'wordpress-import';
				$folder = $dir;
				$counter = 1;
				while(Storage::disk(config('voyager.storage.disk'))->exists($folder)){
					$folder = $dir . (string)$counter;
					$counter += 1;
				}

				Storage::disk(config('voyager.storage.disk'))->makeDirectory($folder);
				
				Storage::disk(config('voyager.storage.disk'))->put($folder . '/wordpress-import.xml', file_get_contents($request->file('wpexport')), 'public');
				$xml_file = Storage::disk(config('voyager.storage.disk'))->url($folder . '/wordpress-import.xml');
				$wp = new WordpressImport($xml_file, $copyImages, $timeout);

				return redirect()->back()->with([
                    'message'    => 'Successfully Imported your WordPress Posts, Pages, Categories, and Users!',
                    'alert-type' => 'success',
                ]);

			} else {
				return redirect()->back()->with([
                    'message'    => 'Invalid file type. Please make sure you are uploading a Wordpress XML export file.',
                    'alert-type' => 'error',
                ]);
			}



		} else {
			return redirect()->back()->with([
                    'message'    => 'Please specify a Wordpress XML file that you would like to upload.',
                    'alert-type' => 'error',
                ]);
		}

	}

}