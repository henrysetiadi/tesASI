<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ClientController extends Controller
{

    public function index()
    {
        return view('posts.index');
    }


    public function show($id)
    {
        $clientKey = "client:$id";  // Redis key for the client (based on ID)

        if (!Redis::exists($clientKey)) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $clientData = Redis::hgetall($clientKey);

        return response()->json([
            'message' => 'Client data retrieved successfully',
            'data' => $clientData
        ]);
    }

    //save data to database
    public function store(Request $request)
    {
        // Validate and store the post
        $request->validate([
            'name' => 'required|string|max:250',
            'slug' => 'required|string|max:100',
            'is_project' => 'required',
            'self_capture' => 'required',
            'client_prefix' => 'required',
            'client_logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        $name = $request->input('name');
        $slug = $request->input('slug');
        $isProject = $request->input('is_project');
        if($isProject == null)
        {
            $isProject = 0;
        }
        else
        {
            $isProject = 1;
        }

        $selfCapture = $request->input('self_capture');
        if($selfCapture == null)
        {
            $selfCapture = 1;
        }

        $clientPrefix = $request->input('client_prefix');


        // Handle the image upload to S3
        if ($request->hasFile('client_logo')) {
            // Store the image on S3
            $path = $request->file('client_logo')->store('client_logo', 's3');

            // Get the full URL of the uploaded image
            $imageUrl = Storage::disk('s3')->url($path);
        } else {
            $imageUrl = 'no-image.jpg';
        }

        $clientLogo = $imageUrl;

        $address = $request->input('address');
        $phoneNumber = $request->input('phone_number');
        $city = $request->input('city');


        $client = Client::create([
            'name' => $name,
            'slug' => $slug,
            'is_project' => $isProject,
            'self_capture' => $selfCapture,
            'client_prefix' => $clientPrefix,
            'client-logo' => $clientLogo,
            'address' => $address,
            'phone_number' => $phoneNumber,
            'city' => $city,
        ]);

        Cache::put('client_' . $client->id, $client, 60);

        return response()->json($client, 201); // Return the saved client
    }

    public function update(Request $request, $id)
    {

        $request->validate([
            'name' => 'required|string|max:250',
            'slug' => 'required|string|max:100',
            'is_project' => 'required',
            'self_capture' => 'required',
            'client_prefix' => 'required',
            'client_logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
        ]);

        $clientKey = "client:$id";

        if (!Redis::exists($clientKey)) {
            return response()->json(['message' => 'Client not found'], 404);
        }


        // find ID Client
        $client = Client::findOrFail($id);

        // Update client data
        $name = $request->input('name');
        $slug = $request->input('slug');
        $isProject = $request->input('is_project');
        if($isProject == null)
        {
            $isProject = 0;
        }
        else
        {
            $isProject = 1;
        }

        $selfCapture = $request->input('self_capture');
        if($selfCapture == null)
        {
            $selfCapture = 1;
        }

        $clientPrefix = $request->input('client_prefix');

        if ($request->hasFile('client_logo')) {
            // Remove old image from S3
            if ($client->client_logo) {
                $oldImagePath = str_replace('https://your-s3-bucket-url/', '', $client->client_logo);
                \Storage::disk('s3')->delete($oldImagePath);
            }

            // Store image on S3
            $path = $request->file('client_logo')->store('client_logo', 's3');
            $imageUrl = \Storage::disk('s3')->url($path);
            $client->client_logo = $imageUrl;
        }

        $clientLogo = $imageUrl;

        $address = $request->input('address');
        $phoneNumber = $request->input('phone_number');
        $city = $request->input('city');

        $data = [
            'name' => $name,
            'slug' => $slug,
            'is_project' => $isProject,
            'self_capture' => $selfCapture,
            'client_prefix' => $clientPrefix,
            'client_logo' => $clientLogo,
            'address' => $address,
            'phone_number' => $phoneNumber,
            'city' => $city
        ];

        Redis::hmset($clientKey, $data);

        return response()->json([
            'message' => 'Successfully update client data',
            'data' => Redis::hgetall($clientKey),
        ]);
    }

    public function delete($id)
    {

        $clientKey = "client:$id";

        if (!Redis::exists($clientKey)) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        Redis::del($clientKey);

        $client = Client::find($id);

        $client->deleted_at = Carbon::now(); // Or any custom timestamp

        $client->save();

        return response()->json(['message' => 'Client data deleted successfully']);
    }
}
