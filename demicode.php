<?php

namespace App\Admin\Actions\Page;

use OpenAdmin\Admin\Actions\Action;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\BrandModel;
use App\Models\Category;
use App\Models\CouponModel;
use App\Models\ImportLogModel;
use Spatie\Sitemap\Sitemap;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class BrandFetch extends Action
{
    public $name = 'Fetch Brand'; // Button name
    public $icon = 'fa-cloud-download'; // Button icon

    protected $selector = '.fetch-brand';

    /**
     * @return string
     */

    function getClientIp()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }
        // Whether IP is from the proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Whether IP is from the remote address
        else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip;
    }



    protected function fetchAdvertiserData()
    {
        $apiUrl = 'https://advertiser-lookup.api.cj.com/v2/advertiser-lookup';
        $requestorCid = '5331660';
        $advertiserIds = 'joined';
        $token = 't0yqs9bdwj5kwrfmzsh2aqv26';

        $response = Http::withToken($token)
            ->get($apiUrl, [
                'requestor-cid' => $requestorCid,
                'advertiser-ids' => $advertiserIds,
            ]);

        $html = $response->getBody()->getContents();
        $xml = simplexml_load_string($html);
        $allData = $xml->advertisers;
        $advertiserData = [];

        foreach ($allData->advertiser as $advertiser) {
            $jsonData = json_encode($advertiser);
            $data = json_decode($jsonData, true);
            $advertiserData[] = $data;
        }

        return $advertiserData;
    }

    function fetchDataFromShareASaleAPI($actionVerb)
    {
        $myAffiliateID = '2999157';
        $APIToken = "D2GH1Q2rUPM5NwPd";
        $APISecretKey = "QSa9af4k7YNcfy0bHGw9zj7j5JAqwc2u";
        $APIVersion = 2.5;

        // Generate timestamp
        $myTimeStamp = gmdate(DATE_RFC1123);
        $dt_ar = explode('+0000', $myTimeStamp);
        $dt_ar[0] = $dt_ar[0] . 'GMT';
        $myTimeStamp = $dt_ar[0];

        // Generate signature
        $sig = $APIToken . ':' . $myTimeStamp . ':' . $actionVerb . ':' . $APISecretKey;
        $sigHash = hash("sha256", $sig);

        // Set headers
        $myHeaders = array("x-ShareASale-Date: $myTimeStamp", "x-ShareASale-Authentication: $sigHash");

        // Initialize cURL
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, "https://api.shareasale.com/x.cfm?affiliateId=$myAffiliateID&token=$APIToken&version=$APIVersion&action=$actionVerb&format=xml");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $myHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        // Execute cURL request
        $returnResult = curl_exec($ch);

        // Check for cURL error
        if ($returnResult === false) {
            echo 'Curl error: ' . curl_error($ch);
            exit;
        }

        // Parse XML response
        $xml = simplexml_load_string($returnResult);

        // Convert XML to JSON and then to array
        $json = json_encode($xml);
        $data = json_decode($json, true);

        // Close cURL
        curl_close($ch);

        // Return the data
        return $data;
    }
    public function handle(Request $request)
    {
        $selectedValue = $request->input('selector');

        if ($selectedValue == 6) {
            // Show form for uploading Excel file
            return view('upload-form', ['selectedValue' => $selectedValue]);
        } else {
            // Handle other cases for different selected values
        }
        if ($selectedValue == 5) {



            
            dd(Sitemap::all());

            die;
            try {


                $actionVerb = "merchantDataFeeds";
                $merchantDataFeedsData = $this->fetchDataFromShareASaleAPI($actionVerb);
                $brandData = $merchantDataFeedsData['datafeedlistreportrecord'];
                foreach ($brandData as $storeitem) {
                    $brandN = $storeitem['merchant'];
                    $brandName = BrandModel::where('brand_name', $brandN)->first();

                    if (!$brandName) {
                        $brand = new BrandModel();
                        $brand->brand_name = $brandN;
                        $brand->brand_logo = '/images/nope.jpg';

                        $actionVerb = "merchantStatus";
                        $merchantStatus = $this->fetchDataFromShareASaleAPI($actionVerb);

                        if (isset($merchantStatus['merchantstatusreportrecord'])) {
                            $brandAffiliate = $merchantStatus['merchantstatusreportrecord'];

                            foreach ($brandAffiliate as $brandAff) {
                                if ($brandAff['merchant'] == $brandN) {
                                    $brand->brand_website = isset($brandAff['linkurl']) ? $brandAff['linkurl'] : '';
                                    break;
                                }
                            }
                        }

                        $brand->brand_desc = '';
                        $brand->active = 1;
                        $brand->save();

                    }

                }
                $actionVerb = "couponDeals";
                $couponDeals = $this->fetchDataFromShareASaleAPI($actionVerb);
                $couponData = $couponDeals['dealcouponlistreportrecord'];

                foreach ($couponData as $cdata) {
                    $brandName = $cdata['merchant'];
                    $brand = BrandModel::where('brand_name', $brandName)->first();

                    // Check if brand exists
                    if ($brand) {
                        $bName = $brand->brand_name;
                        if ($bName == $cdata['merchant']) {
                            $code = $cdata['couponcode'];
                            $couponcode = CouponModel::where('coupon_code', $code)->first();

                            if (!$couponcode) {
                                $coupon = new CouponModel();
                                $coupon->coupon_code = $cdata['couponcode'];
                                $coupon->coupon_desc = isset($cdata['description']) && !is_array($cdata['description']) ? $cdata['description'] : '';
                                if (!empty($cdata['merchant'])) {
                                    // No need to fetch brand again, we already have it
                                    $coupon->brand_id = $brand->brand_id;

                                    if ($brand->brand_website) {
                                        $coupon->affiliate_link = $brand->brand_website;
                                    }
                                }

                                $coupon->keywords = $cdata['merchant'];
                                $coupon->tags = '';
                                $coupon->category_id = 118;

                                $coupon->best_coupon = 1;

                                $coupon->expiry_date = $cdata['enddate'];
                                $coupon->active = 1;
                                // dd($coupon);
                                $coupon->save();
                            }
                        }
                    }
                }

            } catch (\Exception $e) {
                dd($e->getMessage());
            }


            return $this->response()->success('All Data Fetch ')->refresh();
        }


        if ($selectedValue == 4) {


            $apiUrl = 'https://bountii.com/wp-json/bountii/v1/getBlogs?type=coupon';
            $response = Http::get($apiUrl);
            $data = $response->json();
            $categorydata = $data['empty_terms']['coupon-category'];
            $Storedata = $data['empty_terms']['coupon-store'];
            dd($Storedata);
            // foreach ($categorydata as $Catitem) {
            //     $categoryName = $Catitem['name'];
            //     $categoryExists = Category::where('category_name', $categoryName)->exists();

            //     if (!$categoryExists) {
            //         $category = new Category();
            //         $category->category_name = $categoryName;
            //         $category->active = 1;
            //         $category->save();
            //     }
            // }

            foreach ($Storedata as $storeitem) {

                $brandN = $storeitem['name'];
                $brandName = BrandModel::where('brand_name', $brandN)->first();

                if (!$brandName) {

                    $brand = new BrandModel();
                    $brand->brand_name = $storeitem['name'];
                    $imageUrl = $storeitem['image'];
                    $imageContent = file_get_contents($imageUrl);
                    $filename = basename($imageUrl);
                    Storage::disk('admin')->put('/images/' . $filename, $imageContent);
                    $brand->brand_logo = '/images/' . $filename;
                    $brand->brand_website = isset($storeitem['affiliate_link']) ? $storeitem['affiliate_link'] : '';
                    $cDesc = $storeitem['description'];
                    $description = str_replace(["\n", "\r"], ' ', $cDesc);
                    $brand->brand_desc = $description ?? '';
                    $brand->active = 1;
                    dd($brand);
                    $brand->save();

                }
            }
            return $this->response()->success('All Data Fetch ')->refresh();
        }
        if ($selectedValue == 3) {

            $apiUrl = 'https://bountii.com/wp-json/bountii/v1/getBlogs?type=coupon';
            $response = Http::get($apiUrl);
            $data = $response->json();
            // dd(ImportLogModel::all());


            $dataArray = [];
            $i = 1;
            $descArray[] = '';
            foreach ($data as $item) {

                // $id = $item['ID'];

                // // Check if the "coupon_code" exists and is not null
                // if (isset($item['desc']) && $item['desc'] !== null) {
                //     $code = $item['desc'];

                //     // Fetch all coupon codes that match the provided description
                //     $couponCodes = CouponModel::where('coupon_desc', $code)->get()->pluck('coupon_code')->toArray();

                //     if (in_array($code, $couponCodes)) {
                //         $dataArray[] = $id;
                //         echo "<pre>";
                //         print_r($dataArray);
                //         echo "</pre>";
                //     }
                // }







                // $couponDescs = CouponModel::where('coupon_code', 'AUTO-APPLIED')->first();
                // $couponId = $couponDescs->id;

                // foreach ($couponData as $cdata) {

                //     if ($cdata == $item['ID']) {
                //         $desc = $item['desc'];
                //         $coupondesc = CouponModel::where('coupon_desc', $desc)->first();

                //         if (!$coupondesc) {

                //             $coupon = new CouponModel();
                //             $couponCode = $item['coupon_code'];
                //             $coupon->coupon_code = $couponCode !== null ? $couponCode . $i : 'null' . $i;
                //             $coupon->coupon_desc = $item['desc'];

                //             if (!empty($item['brand_data']['0'])) {
                //                 $brandName = $item['brand_data']['0']['name'];
                //                 $brand = BrandModel::where('brand_name', $brandName)->first();
                //                 $brandId = $brand->brand_id;
                //                 if ($brandId) {
                //                     $coupon->brand_id = $brandId;
                //                 }
                //             } else {
                //                 $coupon->brand_id = 621;
                //             }

                //             $coupon->affiliate_link = isset($item['brand_data']['affiliate_link']) ? $item['brand_data']['affiliate_link'] : '';
                //             $coupon->keywords = isset($item['brand_data']['0']['name']) ? $item['brand_data']['0']['name'] : '';
                //             $coupon->tags = '';
                //             if (!empty($item['category_data']['0'])) {
                //                 $category_name = $item['category_data']['0']['name'];
                //                 $category = Category::where('category_name', $category_name)->first();
                //                 $categoryId = $category->category_id;

                //                 if ($categoryId) {
                //                     $coupon->category_id = $categoryId;
                //                 }
                //             } else {
                //                 $coupon->category_id = 73;
                //             }
                //             $coupon->best_coupon = 1;
                //             $publish_date = $item['publish_date'];
                //             $timestamp = strtotime($publish_date);
                //             $nextMonthTimestamp = strtotime('+1 month', $timestamp);
                //             $expiry_date = date('Y-m-d', $nextMonthTimestamp);
                //             $coupon->expiry_date = $expiry_date;
                //             $coupon->active = 1;
                //             $coupon->save();
                //         }
                //     }

                // }





                // if (!empty($item['brand_data']['0'])) {
                //     //Brand create
                //     try {

                //         $brandN = $item['brand_data']['0']['name'];
                //         $brandName = BrandModel::where('brand_name', $brandN)->first();

                //         if (!$brandName) {


                //             $brand = new BrandModel();
                //             $brand->brand_name = $item['brand_data']['0']['name'];
                //             $imageUrl = $item['brand_data']['image'];
                //             // $imageUrl = 'https://bountii.com/wp-content/uploads/2023/11/Atlas-VPN-200.png';
                //             $imageContent = file_get_contents($imageUrl);
                //             $filename = basename($imageUrl);
                //             Storage::disk('admin')->put('/images/' . $filename, $imageContent);
                //             $brand->brand_logo = '/images/' . $filename;
                //             $brand->brand_website = isset($item['brand_data']['affiliate_link']) ? $item['brand_data']['affiliate_link'] : '';
                //             $brand->brand_desc = $item['brand_data']['0']['description'];
                //             $brand->active = 1;
                //             $brand->save();


                //             //Brand Log
                //             $import_log = new ImportLogModel();

                //             // $brandName = $item['brand_data']['0']['name'];
                //             // $brand = BrandModel::where('brand_name', $brandName)->first();
                //             $brandId = $brand->brand_id;
                //             $import_log->content_id = $brandId;
                //             $import_log->type = 'brand';
                //             $import_log->source = 'bountii.com';
                //             $ip = $this->getClientIp();
                //             $ipAddress = $ip;

                //             $import_log->ip = $ipAddress;
                //             $import_log->save();



                //         }

                //     } catch (\Exception $e) {
                //         dd($e->getMessage());
                //     }
                // } else {
                //     $dataArray[] = [
                //         'Coupon-ID' => $item['ID'],
                //         'Brand-Data' => 0,
                //     ];
                // }
                // if (!empty($item['category_data']['0'])) {
                //     //category create
                //     try {
                //          foreach ($item['category_data'] as $categoryData) {
                //             $categoryName = $categoryData['name'];
                //             $categoryExists = Category::where('category_name', $categoryName)->exists();

                //             if(!$categoryExists) {
                //                 $category = new Category();
                //                 $category->category_name = $categoryName;
                //                 $category->active = 1;
                //                 $category->save();
                //             }

                //             //Category Log
                //             $import_log = new ImportLogModel();
                //             $categoryId = $category->category_id;

                //             $import_log->content_id = $categoryId;

                //             $import_log->type = 'category';
                //             $import_log->source = 'bountii.com';

                //             $ip = $this->getClientIp();
                //             $ipAddress = $ip;

                //             $import_log->ip = $ipAddress;
                //             $import_log->save();
                //         }
                //     } catch (\Exception $e) {
                //         dd($e->getMessage()); // This will display the error message
                //     }

                // }else{
                //     $dataArray[] = [
                //         'Coupon-ID' => $item['ID'],
                //         'Category-Data' => 0,
                //     ]; 
                // }

                // if (!empty($item['brand_data']['0'])) {
                //     if (!empty($item['category_data']['0'])) {
                //         //coupon
                //         try {
                //             $coupon_code = $item['coupon_code'];
                //             $couponCode = CouponModel::where('coupon_code', $coupon_code)->first();
                //             if (!$couponCode) {
                //                 $coupon = new CouponModel();
                //                 $couponCode = $item['coupon_code'];
                //                 $coupon->coupon_code = $couponCode !== null ? $couponCode : 'null' . $i;
                //                 $coupon->coupon_desc = $item['desc'];

                //                 $brandName = $item['brand_data']['0']['name'];
                //                 $brand = BrandModel::where('brand_name', $brandName)->first();
                //                 $brandId = $brand->brand_id;
                //                 if ($brandId) {
                //                     $coupon->brand_id = $brandId;
                //                 }

                //                 $coupon->affiliate_link = isset($item['brand_data']['affiliate_link']) ? $item['brand_data']['affiliate_link'] : '';
                //                 $coupon->keywords = isset($item['brand_data']['0']['name']) ? $item['brand_data']['0']['name'] : '';
                //                 $coupon->tags = '';

                //                 $category_name = $item['category_data']['0']['name'];
                //                 $category = Category::where('category_name', $category_name)->first();
                //                 $categoryId = $category->category_id;
                //                 if ($categoryId) {
                //                     $coupon->category_id = $categoryId;
                //                 }

                //                 $coupon->best_coupon = 1;
                //                 $publish_date = $item['publish_date'];
                //                 $timestamp = strtotime($publish_date);
                //                 $nextMonthTimestamp = strtotime('+1 month', $timestamp);
                //                 $expiry_date = date('Y-m-d', $nextMonthTimestamp);
                //                 $coupon->expiry_date = $expiry_date;
                //                 $coupon->active = 1;
                //                 $coupon->save();

                //                 //Coupon Log
                //                 $import_log = new ImportLogModel();
                //                 $couponId = $coupon->id;

                //                 $import_log->content_id = $couponId;

                //                 $import_log->type = 'coupon';
                //                 $import_log->source = 'bountii.com';

                //                 $ip = $this->getClientIp();
                //                 $ipAddress = $ip;

                //                 $import_log->ip = $ipAddress;
                //                 $import_log->save();

                //             }
                //         } catch (\Exception $e) {
                //             dd($e->getMessage());
                //         }
                //     }
                // }
                $i++;

            }

            $code = var_export($dataArray, true);
            $filePath = 'missingCouponData.php'; // Ensure correct file path
            Storage::disk('admin')->put($filePath, '<?php return ' . $code . ';');

            return $this->response()->success('All Data Fetch ')->refresh();
        }



            if ($selectedValue == 1) {

            $brands = BrandModel::all();
            foreach ($brands as $brand) {
            $brandName = $brand->brand_name;
                $newDescription = "desc";
                $brand->brand_desc = $newDescription;
                $brand->save();

            } 
            return $this->response()->success('Data Update')->refresh();
            // die;
            // $advertiserData = $this->fetchAdvertiserData();
            // foreach ($advertiserData as $item) {
            //     //create a category
            //     $category = $item['primary-category']['parent'];
            //     $categoryName = Category::where('category_name', $category)->first();
            //     if (!$categoryName) {
            //         $category = new Category();
            //         $category->category_name = $item['primary-category']['parent'];
            //         $category->active = ($item['account-status'] === 'Active') ? 1 : 0;
            //         $category->save();

            //         //Category Log
            //         $import_log = new ImportLogModel();

            //         $category_name = $item['primary-category']['parent'];
            //         $category = Category::where('category_name', $category_name)->first();
            //         $categoryId = $category->category_id;

            //         $import_log->content_id = $categoryId;

            //         $import_log->type = 'category';
            //         $import_log->source = 'Cj.com';

            //         $ip = $this->getClientIp();
            //         $ipAddress = $ip;

            //         $import_log->ip = $ipAddress;
            //         $import_log->save();
            //     }


            //     //create a brand
            //     $brandN = $item['advertiser-name'];
            //     $brandName = BrandModel::where('brand_name', $brandN)->first();
            //     if (!$brandName) {
            //         $brand = new BrandModel();
            //         $brand->brand_name = $item['advertiser-name'];
            //         $brand->brand_logo = '/images/nope.jpg';
            //         $brand->brand_website = $item['program-url'];
            //         $brand->brand_desc = "";
            //         $brand->active = ($item['account-status'] === 'Active') ? 1 : 0;
            //         $brand->save();


            //         //Brand Log
            //         $import_log = new ImportLogModel();

            //         $brandName = $item['advertiser-name'];
            //         $brand = BrandModel::where('brand_name', $brandName)->first();
            //         $brandId = $brand->brand_id;
            //         $import_log->content_id = $brandId;

            //         $import_log->type = 'brand';
            //         $import_log->source = 'Cj.com';

            //         $ip = $this->getClientIp();
            //         $ipAddress = $ip;

            //         $import_log->ip = $ipAddress;
            //         $import_log->save();
            //     }


            // }

        } else {
            dd($selectedValue);
        }

        // Return a success response if no error occurs
        return $this->response()->success('All Category & Store Data Fetch ')->refresh();
    }

    public function form()
    {
        $this->select('selector', 'Select Company')->options([1 => 'cj.com', 2 => 'impact.com', 3 => 'bounti.com', 4 => 'fetch Cat & Brand', 5 => 'shareasale.com',6 => 'upload images']);
        $this->interactor->form->model = new BrandModel();
    }

    public function html()
    {
        return <<<HTML
        <a class="btn btn-sm btn-warning fetch-brand"><i class='icon-file-import'></i>Fetch Brand</a>
        HTML;
    }

}
