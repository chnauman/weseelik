<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserBlockResource;
use App\Models\Garage;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class UserBlockApiController extends Controller
{
    /**
     * Get all blocked garages for the authenticated user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $blocks = UserBlock::with(['garage'])->where('user_id', $user->id)->get();

        return response()->json([
            'success' => true,
            'count' => $blocks->count(),
            'data' => UserBlockResource::collection($blocks)
        ]);
    }

    /**
     * Block a garage
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function blockGarage(Request $request)
    {
        // Validate request
        $request->validate([
            'garage_id' => 'required|exists:garages,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        // Get the authenticated user
        $user = $request->user();
        
        // Check if this garage is already blocked by the user (including soft deleted)
        $existingBlock = UserBlock::withTrashed()
            ->where('user_id', $user->id)
            ->where('garage_id', $request->garage_id)
            ->first();
            
        if ($existingBlock) {
            if ($existingBlock->trashed()) {
                // If it was previously blocked and deleted, restore it
                $existingBlock->restore();
                $existingBlock->update(['reason' => $request->reason]);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Garage blocked successfully',
                    'data' => new UserBlockResource($existingBlock)
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'This garage is already blocked',
                'data' => new UserBlockResource($existingBlock)
            ]);
        }
        
        // Create new block
        $userBlock = UserBlock::create([
            'user_id' => $user->id,
            'garage_id' => $request->garage_id,
            'reason' => $request->reason,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Garage blocked successfully',
            'data' => new UserBlockResource($userBlock)
        ], Response::HTTP_CREATED);
    }
    
    /**
     * Unblock a garage
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unblockGarage(Request $request)
    {
        // Validate request
        $request->validate([
            'garage_id' => 'required|exists:garages,id',
        ]);
        
        // Get the authenticated user
        $user = $request->user();
        
        // Find the block
        $userBlock = UserBlock::where('user_id', $user->id)
            ->where('garage_id', $request->garage_id)
            ->first();
            
        if (!$userBlock) {
            return response()->json([
                'success' => false,
                'message' => 'This garage is not blocked by you'
            ], Response::HTTP_NOT_FOUND);
        }
        
        // Delete the block
        $userBlock->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Garage unblocked successfully'
        ]);
    }

    /**
     * Check if a garage is blocked by the user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function isBlocked(Request $request)
    {
        // Validate request
        $request->validate([
            'garage_id' => 'required|exists:garages,id',
        ]);
        
        // Get the authenticated user
        $user = $request->user();
        
        // Check if this garage is blocked by the user
        $isBlocked = UserBlock::where('user_id', $user->id)
            ->where('garage_id', $request->garage_id)
            ->exists();
            
        return response()->json([
            'success' => true,
            'is_blocked' => $isBlocked
        ]);
    }
}
