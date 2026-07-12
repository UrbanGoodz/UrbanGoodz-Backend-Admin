<?php

namespace App\Services;
use App\CentralLogics\Helpers;
use App\Models\DeliveryMan;
use App\Traits\FileManagerTrait;


class DeliveryManService
{
    use FileManagerTrait;

    public function getAddData(Object $request): array
    {
        if ($request->has('image')) {
            $imageName = $this->upload('delivery-man/', 'png', $request->file('image'));
        } else {
            $imageName = 'def.png';
        }

        $identityImageNames = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identityImage = $this->upload('delivery-man/', 'png', $img);
                array_push($identityImageNames, ['img'=>$identityImage, 'storage'=> Helpers::getDisk()]);
            }
            $identityImage = json_encode($identityImageNames);
        } else {
            $identityImage = json_encode([]);
        }

        if($request->referral_code){
            $referal_user = DeliveryMan::where('ref_code',$request->referral_code)->first();
            Helpers::deliverymanReferralNotification($referal_user);
        }

        $data = [
            'f_name' => $request->f_name,
            'l_name' => $request->l_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'identity_number' => $request->identity_number,
            'identity_type' => $request->identity_type,
            'vehicle_id' => $request->vehicle_id,
            'vehicle_type' => $request->vehicle_type ?? null,
            'vehicle_make' => $request->vehicle_make ?? null,
            'vehicle_model' => $request->vehicle_model ?? null,
            'vehicle_year' => $request->vehicle_year ?? null,
            'vehicle_color' => $request->vehicle_color ?? null,
            'vehicle_vin' => $request->vehicle_vin ?? null,
            'license_plate' => $request->license_plate ?? null,
            'trailer_vin' => $request->trailer_vin ?? null,
            'trailer_make' => $request->trailer_make ?? null,
            'trailer_model' => $request->trailer_model ?? null,
            'cdl_state' => $request->cdl_state ?? null,
            'cdl_expiration' => $request->cdl_expiration ?? null,
            'usdot_number' => $request->usdot_number ?? null,
            'insurance_policy' => $request->insurance_policy ?? null,
            'insurance_carrier' => $request->insurance_carrier ?? null,
            'load_board_eligible' => $request->load_board_eligible ?? false,
            'zone_id' => $request->zone_id,
            'identity_image' => $identityImage,
            'image' => $imageName,
            'active' => 0,
            'earning' => $request->earning,
            'password' => bcrypt($request->password),
            'ref_by' =>  $request->earning ? $referal_user?->id??null : null,
            'ref_code' => Helpers::generate_referer_code('deliveryman'),
            'is_delivery' => in_array('delivery', $request->serve_for ?? []) ? 1 : 0,
            'is_ride' => in_array('ride', $request->serve_for ?? []) ? 1 : 0,
            'has_trailer' => $request->has_trailer ?? false,
            'trailer_type' => $request->trailer_type ?? null,
            'trailer_length_feet' => $request->trailer_length_feet ?? null,
            'trailer_width_feet' => $request->trailer_width_feet ?? null,
            'trailer_capacity_lbs' => $request->trailer_capacity_lbs ?? null,
            'hitch_type' => $request->hitch_type ?? null,
            'trailer_plate_number' => $request->trailer_plate_number ?? null,
            'trailer_registration_expiration' => $request->trailer_registration_expiration ?? null,
            'trailer_insurance_expiration' => $request->trailer_insurance_expiration ?? null,
            'cdl_status' => $request->cdl_status ?? 'none',
            'cdl_class' => $request->cdl_class ?? null,
            'cdl_number' => $request->cdl_number ?? null,
            'dot_number' => $request->dot_number ?? null,
            'mc_number' => $request->mc_number ?? null,
            'has_pallet_jack' => $request->has_pallet_jack ?? false,
            'has_hazmat' => $request->has_hazmat ?? false,
            'has_cargo_insurance' => $request->has_cargo_insurance ?? false,
            'cargo_insurance_expiration' => $request->cargo_insurance_expiration ?? null,
            'max_payload_lbs' => $request->max_payload_lbs ?? null,
            'cargo_length_inches' => $request->cargo_length_inches ?? null,
            'cargo_width_inches' => $request->cargo_width_inches ?? null,
            'cargo_height_inches' => $request->cargo_height_inches ?? null,
            'registration_expiration' => $request->registration_expiration ?? null,
            'insurance_expiration' => $request->insurance_expiration ?? null,
            'inspection_expiration' => $request->inspection_expiration ?? null,
        ];

        if (addon_published_status('RideShare')) {
            $data['user_level_id'] = $request->user_level_id ?? null;
        }
        return $data;
    }

    public function getUpdateData(Object $request, Object $deliveryMan): array
    {
        if ($request->has('image')) {
            $imageName = $this->updateAndUpload('delivery-man/', $deliveryMan->image, 'png', $request->file('image'));
        } else {
            $imageName = $deliveryMan['image'];
        }

        $currentImages = json_decode($deliveryMan['identity_image'], true) ?? [];

        if ($request->has('delete_identity_image')) {
            foreach ($request->delete_identity_image as $delImg) {
                foreach ($currentImages as $key => $imgData) {
                    $imgName = is_array($imgData) ? $imgData['img'] : $imgData;
                    if ($imgName === $delImg) {
                        Helpers::check_and_delete('delivery-man/' , $imgData);
                        unset($currentImages[$key]);
                    }
                }
            }
            $currentImages = array_values($currentImages);
        }

        if ($request->has('identity_image')){
            foreach ($request->identity_image as $img) {
                $identityImage = $this->upload('delivery-man/', 'png', $img);
                array_push($currentImages, ['img'=>$identityImage, 'storage'=> Helpers::getDisk()]);
            }
        }

        $identityImage = json_encode($currentImages);

        return [
            "f_name" => $request->f_name,
            "l_name" => $request->l_name,
            "email" => $request->email,
            "phone" => $request->phone,
            "identity_number" => $request->identity_number,
            "vehicle_id" => $request->vehicle_id,
            "vehicle_type" => $request->vehicle_type ?? $deliveryMan->vehicle_type,
            "vehicle_make" => $request->vehicle_make ?? $deliveryMan->vehicle_make,
            "vehicle_model" => $request->vehicle_model ?? $deliveryMan->vehicle_model,
            "vehicle_year" => $request->vehicle_year ?? $deliveryMan->vehicle_year,
            "vehicle_color" => $request->vehicle_color ?? $deliveryMan->vehicle_color,
            "vehicle_vin" => $request->vehicle_vin ?? $deliveryMan->vehicle_vin,
            "license_plate" => $request->license_plate ?? $deliveryMan->license_plate,
            "trailer_vin" => $request->trailer_vin ?? $deliveryMan->trailer_vin,
            "trailer_make" => $request->trailer_make ?? $deliveryMan->trailer_make,
            "trailer_model" => $request->trailer_model ?? $deliveryMan->trailer_model,
            "cdl_state" => $request->cdl_state ?? $deliveryMan->cdl_state,
            "cdl_expiration" => $request->cdl_expiration ?? $deliveryMan->cdl_expiration,
            "usdot_number" => $request->usdot_number ?? $deliveryMan->usdot_number,
            "insurance_policy" => $request->insurance_policy ?? $deliveryMan->insurance_policy,
            "insurance_carrier" => $request->insurance_carrier ?? $deliveryMan->insurance_carrier,
            "load_board_eligible" => $request->load_board_eligible ?? $deliveryMan->load_board_eligible,
            "identity_type" => $request->identity_type,
            "zone_id" => $request->zone_id,
            "identity_image" => $identityImage,
            "image" => $imageName,
            "earning" => $request->earning,
            "password" => strlen($request->password)>1?bcrypt($request->password):$deliveryMan['password'],
            "application_status" => in_array($deliveryMan['application_status'], ['pending','denied']) ? 'approved' : $deliveryMan['application_status'],
            "status" => in_array($deliveryMan['application_status'], ['pending','denied']) ? 1 : $deliveryMan['status'],
            "is_delivery" => in_array('delivery', $request->serve_for ?? []) ? 1 : 0,
            "is_ride" => in_array('ride', $request->serve_for ?? []) ? 1 : 0,
            "has_trailer" => $request->has_trailer ?? false,
            "trailer_type" => $request->trailer_type ?? null,
            "trailer_length_feet" => $request->trailer_length_feet ?? null,
            "trailer_width_feet" => $request->trailer_width_feet ?? null,
            "trailer_capacity_lbs" => $request->trailer_capacity_lbs ?? null,
            "hitch_type" => $request->hitch_type ?? null,
            "trailer_plate_number" => $request->trailer_plate_number ?? null,
            "trailer_registration_expiration" => $request->trailer_registration_expiration ?? null,
            "trailer_insurance_expiration" => $request->trailer_insurance_expiration ?? null,
            "cdl_status" => $request->cdl_status ?? 'none',
            "cdl_class" => $request->cdl_class ?? null,
            "cdl_number" => $request->cdl_number ?? null,
            "dot_number" => $request->dot_number ?? null,
            "mc_number" => $request->mc_number ?? null,
            "has_pallet_jack" => $request->has_pallet_jack ?? false,
            "has_hazmat" => $request->has_hazmat ?? false,
            "has_cargo_insurance" => $request->has_cargo_insurance ?? false,
            "cargo_insurance_expiration" => $request->cargo_insurance_expiration ?? null,
            "max_payload_lbs" => $request->max_payload_lbs ?? null,
            "cargo_length_inches" => $request->cargo_length_inches ?? null,
            "cargo_width_inches" => $request->cargo_width_inches ?? null,
            "cargo_height_inches" => $request->cargo_height_inches ?? null,
            "registration_expiration" => $request->registration_expiration ?? null,
            "insurance_expiration" => $request->insurance_expiration ?? null,
            "inspection_expiration" => $request->inspection_expiration ?? null,
        ];
    }

}
