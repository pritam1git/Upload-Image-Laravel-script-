<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use App\Models\BrandModel;

class ImageController extends Controller
{
    public function imageUpload(Request $request)
    {
        $selectedValue = $request->input('selected_value');
        
        if ($selectedValue == 6) {
            $request->validate([
                'file' => 'required|mimes:csv,txt'
            ]);
    
            $file = $request->file('file');
            $filePath = $file->getPathname();
            $fileHandle = fopen($filePath, 'r');

            fgetcsv($fileHandle, 1000, ',');
            $savedImages = [];
            $existingImages = [];
            $failedImages = [];
    
            while (($data = fgetcsv($fileHandle, 1000, ',')) !== FALSE) {
                $imageUrl = $data[4];
    
                if (!empty($imageUrl)) {
                    $result = $this->saveImage($imageUrl);
                    if(!empty($result)){
                        if ($result['status'] == 'saved') {
                        $savedImages[] = $result['message'];
                        } elseif ($result['status'] == 'exists') {
                            $existingImages[] = $result['message'];
                        } else {
                            $failedImages[] = $result['message'];
                        }
                    }
                }
            }
    
            fclose($fileHandle);
            
            return response()->json([
                'message' => 'Image processing complete.',
                'savedImages' => $savedImages,
                'existingImages' => $existingImages,
                'failedImages' => $failedImages,
            ], 200, [], JSON_PRETTY_PRINT);
        } else {
            return response()->json(['message' => 'Invalid selection value.'], 400);
        }
    }
    public function saveImage($imageUrl)
    {
        $brandSlug = pathinfo($imageUrl, PATHINFO_FILENAME);
        $brandSlug = str_replace('-and-', '-', $brandSlug);
        $brandSlug = str_replace('.', '', $brandSlug);
        $filename = basename($imageUrl);
        $storagePath = 'images/' . $filename; 
        
        if (!Storage::disk('admin')->exists($storagePath)) {
    
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])->get($imageUrl);
            
            if ($response->successful()) {
                $brand = BrandModel::where('slug', $brandSlug)->first();
                if ($brand) {
                    $brand->brand_logo = $storagePath;
                    $brand->save(); 
                    
                    $imageContent = $response->body();
                    Storage::disk('admin')->put($storagePath, $imageContent);
                    return ['status' => 'saved', 'message' => $filename . ' has been successfully saved'];
                } else {
                    return ['status' => 'failed', 'message' => 'Brand with slug ' . $brandSlug . ' not found.'];
                }
            } else {
                //Log::error('Failed to fetch image: ' . $imageUrl . ' - Status: ' . $response->status());
                return ['status' => 'failed', 'message' => 'Failed to fetch image: ' . $filename];
            }
        } else {
            return ['status' => 'exists', 'message' => $filename . ' already exists in storage.'];
        }
    }
    
        
    
    
    public function asController(Request $request)
    {
        return $this->imageUpload($request);
    }
    
}
    

    
