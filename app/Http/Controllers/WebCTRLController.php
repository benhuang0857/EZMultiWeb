<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\WebSites;
use Validator;
use ZipArchive;

class WebCTRLController extends Controller
{
    public function create(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'cid'           => 'required|integer|min:0',
                'site_name'     => 'required|string|unique:websites',
                'domain'        => 'required|string|unique:websites',
                'template_id'   => 'required|string|min:0',
                'db_port'       => 'required|string|unique:websites',
                'site_port'     => 'required|string|unique:websites'
            ]);

            if ($validator->fails()) {
                return $validator->errors();
            }

            /* Init website */
            $zip_source_file = 'wp-ezweb.zip';
            $resp = $this->get_docker_status();
            if($resp['code'] == true)
                $resp = $this->check_directory($request->site_name);
            if($resp['code'] == true)
                $resp = $this->check_sitename($request->site_name);
            if($resp['code'] == true)
                $resp = $this->unzip_and_rename($zip_source_file, $request->site_name);
            if($resp['code'] == true)
                $resp = $this->copy_env($request->site_name, $request->site_port, $request->db_port);
            if($resp['code'] == true)
                $resp = $this->copy_wp_auto_config($request->site_name, $request->template_id);
            if($resp['code'] == true)
                $resp = $this->copy_make_file($request->site_name, $request->template_id);
            if($resp['code'] == true)
                $resp = $this->copy_template($request->site_name, $request->template_id);
            if($resp['code'] == true)
            {
                $website = new WebSites;
                $website->cid = $request->cid ?? null;
                $website->site_name = $request->site_name;
                $website->domain = $request->domain;
                $website->template_id = $request->template_id ?? 1;
                $website->db_port = $request->db_port;
                $website->site_port = $request->site_port;
                $website->state = "creating";
                $website->save();

                $resp = [
                    'data' => 'Create website success',
                    'code' => true,
                ];
            }
            
            $viewBag = [
                'message' => $resp['data']
            ];

            return response()->json(['code' => http_response_code(), 'data' => $viewBag]);
        } catch (Exception  $e) {
            return response()->json(['code' => http_response_code(), 'data' => $e]);
        }
    }

    public function kill(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'ids.*'         => 'required|string'
            ]);

            if ($validator->fails()) {
                return $validator->errors();
            }

            $ids = $request->input('ids');
            // WebSites::whereIn('id', $ids)->delete();
            foreach ($ids as $id) {
                $webSite = WebSites::where('id', $id)->first();
                $this->kill_containers($webSite->site_name);
                $this->kill_directory($webSite->site_name);
                WebSites::where('id', $id)->delete();
            }
            
            $viewBag = [
                'data' => 'Delete WebSites success'
            ];

            return response()->json(['code' => http_response_code(), 'data' => $viewBag]);
        } catch (Exception  $e) {
            return response()->json(['code' => http_response_code(), 'data' => $e]);
        }
    }

    public function get_docker_status()
    {
        $viewBag = [];
        $client = new Client();
        
        try {
            $response = $client->request('GET', env('AUTOCTRL_URI') . '/docker-status');
            $data = json_decode($response->getBody(), true);

            if($data['code'] == 200 && $data['data'] == 'Docker daemon is alive')
            {
                $viewBag = [
                    'code' => true,
                    'data' => 'Docker daemon is alive'
                ];
            }
            else
            {
                $viewBag = [
                    'code' => false,
                    'data' => $data['data']
                ];
            }

            return $viewBag;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Failed to connect to FastAPI: ' . $e->getMessage());
            
            $viewBag = [
                'code' => false,
                'data' => 'Failed to connect to FastAPI: ' . $e->getMessage()
            ];

            return $viewBag;
        }
    }

    public function check_directory($dirname)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $targetDirectory = realpath($currentDirectory . '/../..') . '/' . $dirname;
        
        if (!is_dir($targetDirectory)) {
            $viewBag = [
                'code' => true,
                'data' => 'directory not exists, can create directory'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'directory already exists'
            ];
        }
        return $viewBag;
    }

    public function check_sitename($site_name)
    {
        $viewBag = [];
        $website = WebSites::where('site_name', $site_name)->first();

        if ($website == null) {
            $viewBag = [
                'code' => true,
                'data' => 'site name does not exist, can create site'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'site name already exists'
            ];
        }

        return $viewBag;
    }

    public function unzip_and_rename($zip_file, $new_folder_name)
    {
        $viewBag = [];
        $zip = new ZipArchive;
        $currentDirectory = getcwd();
        $targetZip = realpath($currentDirectory . '/../..') . '/' . $zip_file;
        $targetDirectory = realpath($currentDirectory . '/../..') . '/' . $new_folder_name;

        if ($zip->open($targetZip) === TRUE) {
            mkdir($targetDirectory, 0777, true);
            $zip->extractTo($targetDirectory);
            $zip->close();
            $viewBag = [
                'code' => true,
                'data' => 'unzip successful'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'failed to unzip'
            ];
        }

        return $viewBag;
    }

    public function copy_env($site_name, $site_port, $db_port)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $envOrg = $sourcePath . '/.env.org';

        if (!file_exists($envOrg)) {
            $viewBag = [
                'code' => false,
                'data' => 'original env file not found'
            ];
            return $viewBag;
        }

        $content = file_get_contents($envOrg);
        if ($content === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to read env file'
            ];
            return $viewBag;
        }

        $replaced = str_replace(
            ['{{project_name}}', '{{site_port}}', '{{sql_port}}'],
            [$site_name, $site_port, $db_port],
            $content
        );

        if (file_put_contents($sourcePath . '/.env', $replaced) === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to write env file'
            ];
        } else {
            $viewBag = [
                'code' => true,
                'data' => 'env file copied and replaced successfully'
            ];
        }

        return $viewBag;
    }

    public function copy_wp_auto_config($site_name, $template_id)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $fileOrg = $sourcePath . '/wp-auto-config.yml.org';

        if (!file_exists($fileOrg)) {
            $viewBag = [
                'code' => false,
                'data' => 'original wp-auto-config file not found'
            ];
            return $viewBag;
        }

        $content = file_get_contents($fileOrg);
        if ($content === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to read wp-auto-config file'
            ];
            return $viewBag;
        }

        $replaced = str_replace(
            ['{{template_id}}'],
            [$template_id],
            $content
        );

        if (file_put_contents($sourcePath . '/wp-auto-config.yml', $replaced) === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to write wp-auto-config file'
            ];
        } else {
            $viewBag = [
                'code' => true,
                'data' => 'wp-auto-config file copied and replaced successfully'
            ];
        }

        return $viewBag;
    }

    public function copy_template($site_name, $template_id)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $theme_wpress = "default-ai1wm-stable-" . $template_id . ".wpress";
        $sourceFile = realpath($currentDirectory . '/../..') . '/theme' . '/' . $theme_wpress;
        $targetPath = realpath($currentDirectory . '/../..') . '/' . $site_name . '/config/wordpress/' . $theme_wpress;

        if (!file_exists($sourceFile)) {
            $viewBag = [
                'code' => false,
                'data' => 'source template file not found'
            ];
        } elseif (copy($sourceFile, $targetPath)) {
            $viewBag = [
                'code' => true,
                'data' => 'template file copied successfully'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'failed to copy template file'
            ];
        }

        return $viewBag;
    }

    public function copy_make_file($site_name, $template_id)
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $sourcePath = realpath($currentDirectory . '/../..') . '/' . $site_name;
        $fileOrg = $sourcePath . '/wpcli/Makefile.org';

        if (!file_exists($fileOrg)) {
            $viewBag = [
                'code' => false,
                'data' => 'original Makefile not found'
            ];
            return $viewBag;
        }

        $content = file_get_contents($fileOrg);
        if ($content === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to read Makefile'
            ];
            return $viewBag;
        }

        $replaced = str_replace(
            ['{{template_id}}'],
            [$template_id],
            $content
        );

        if (file_put_contents($sourcePath . '/wpcli/Makefile', $replaced) === false) {
            $viewBag = [
                'code' => false,
                'data' => 'failed to write Makefile'
            ];
        } else {
            $viewBag = [
                'code' => true,
                'data' => 'Makefile copied and replaced successfully'
            ];
        }

        return $viewBag;
    }

    public function kill_directory($site_name) 
    {
        $viewBag = [];
        $currentDirectory = getcwd();
        $target_dir = realpath($currentDirectory . '/../..') . '/' . $site_name;

        if (!file_exists($target_dir) || !is_dir($target_dir)) {
            $viewBag = [
                'code' => false,
                'data' => 'Folder not found'
            ];
            return $viewBag;
        }
    
        if ($this->deleteDirectory($target_dir)) {
            $viewBag = [
                'code' => true,
                'data' => 'Folder deleted successfully'
            ];
        } else {
            $viewBag = [
                'code' => false,
                'data' => 'Failed to delete folder'
            ];
        }
        
        return $viewBag;
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return false;
        }
    
        if (!is_dir($dir)) {
            return unlink($dir);
        }
    
        $items = array_diff(scandir($dir), ['.', '..']);
    
        foreach ($items as $item) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        return rmdir($dir);
    }

    public function kill_containers($site_name)
    {
        $viewBag = [];
        $client = new Client();
        
        try {
            $response = $client->request('GET', env('AUTOCTRL_URI') . '/kill-containers?site_name='.$site_name);
            $data = json_decode($response->getBody(), true);

            if($data['code'] == 200)
            {
                $viewBag = [
                    'code' => true,
                    'data' => $data['data']
                ];
            }
            else
            {
                $viewBag = [
                    'code' => false,
                    'data' => $data['data']
                ];
            }

            return $viewBag;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            Log::error('Failed to connect to FastAPI: ' . $e->getMessage());
            
            $viewBag = [
                'code' => false,
                'data' => 'Failed to connect to FastAPI: ' . $e->getMessage()
            ];

            return $viewBag;
        }
    }

}
