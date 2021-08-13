<?php
namespace Bookly\Backend\Components\Dialogs\Service\Edit;

use Bookly\Lib;
use Bookly\Backend\Modules\Services\Proxy as ServicesProxy;
use Bookly\Backend\Modules\Services\Page as ServicesPage;

/**
 * Class Ajax
 * @package Bookly\Backend\Modules\Services
 */
class Ajax extends Lib\Base\Ajax
{
    /**
     * Edit Service
     */
    public static function getServiceData()
    {
        $service_id              = self::parameter( 'id' );
        $service_collection_data = Lib\Entities\Service::query( 's' )
            ->select( 's.*, COUNT(staff.id) AS total_staff, GROUP_CONCAT(DISTINCT staff.id) AS staff_ids' )
            ->leftJoin( 'StaffService', 'ss', 'ss.service_id = s.id' )
            ->leftJoin( 'Staff', 'staff', 'staff.id = ss.staff_id' )
            ->whereIn( 's.type', array_keys( ServicesProxy\Shared::prepareServiceTypes( array( Lib\Entities\Service::TYPE_SIMPLE => Lib\Entities\Service::TYPE_SIMPLE ) ) ) )
            ->groupBy( 's.id' )
            ->fetchArray();
        $service_collection      = array();
        foreach ( $service_collection_data as $current_service ) {
            if ( $current_service['id'] == $service_id ) {
                $service = $current_service;
            }
            $service_collection[ $current_service['id'] ] = $current_service;
        }
        $service['sub_services']       = Lib\Entities\SubService::query()
            ->where( 'service_id', $service['id'] )
            ->sortBy( 'position' )
            ->fetchArray();
        $service['sub_services_count'] = array_sum( array_map( function ( $sub_service ) {
            return (int) ( $sub_service['type'] == Lib\Entities\SubService::TYPE_SERVICE );
        }, $service['sub_services'] ) );
        $service['colors']             = ServicesProxy\Shared::prepareServiceColors( array_fill( 0, 3, $service['color'] ), $service['id'], $service['type'] );

        $staff_dropdown_data = ServicesPage::getStaffDropDownData();

        $categories_collection = Lib\Entities\Category::query()->sortBy( 'position' )->fetchArray();
        $service_types         = ServicesProxy\Shared::prepareServiceTypes( array( Lib\Entities\Service::TYPE_SIMPLE => __( 'Simple', 'bookly' ) ) );
        $result                = array(
            'general_html'      => self::renderTemplate( 'general', compact( 'service', 'service_types', 'service_collection', 'staff_dropdown_data', 'categories_collection' ), false ),
            'advanced_html'     => Proxy\Pro::getAdvancedHtml( $service, $service_types, $service_collection, $staff_dropdown_data, $categories_collection ),
            'time_html'         => self::renderTemplate( 'time', compact( 'service', 'service_types', 'service_collection', 'staff_dropdown_data', 'categories_collection' ), false ),
            'extras_html'       => Proxy\ServiceExtras::getTabHtml( $service_id ),
            'schedule_html'     => Proxy\ServiceSchedule::getTabHtml( $service_id ),
            'special_days_html' => Proxy\ServiceSpecialDays::getTabHtml( $service_id ),
            'additional_html'   => Proxy\Shared::prepareAfterServiceList( '', $service_collection ),
            'title'             => $service['title'],
            'type'              => $service['type'],
            'price'             => Lib\Utils\Price::format( $service['price'] ),
            'duration'          => in_array( $service['type'], array(
                Lib\Entities\Service::TYPE_COLLABORATIVE,
                Lib\Entities\Service::TYPE_COMPOUND,
            ) ) ? sprintf( _n( '%d service', '%d services', $service['sub_services_count'], 'bookly' ), $service['sub_services_count'] ) : Lib\Utils\DateTime::secondsToInterval( $service['duration'] ),
            'staff'             => $staff_dropdown_data,
        );

        wp_send_json_success( $result );
    }

    /**
     * Update service parameters and assign staff
     */
    public static function updateService()
    {
        $form = new Forms\Service();
        $form->bind( self::postParameters() );
        $service = $form->save();

        $staff_ids = self::parameter( 'staff_ids', array() );
        if ( empty ( $staff_ids ) ) {
            Lib\Entities\StaffService::query()->delete()->where( 'service_id', $service->getId() )->execute();
        } else {
            Lib\Entities\StaffService::query()->delete()->where( 'service_id', $service->getId() )->whereNotIn( 'staff_id', $staff_ids )->execute();
            if ( $service->getType() == Lib\Entities\Service::TYPE_SIMPLE ) {
                if ( self::parameter( 'update_staff', false ) ) {
                    Lib\Entities\StaffService::query()
                        ->update()
                        ->set( 'price', self::parameter( 'price' ) )
                        ->set( 'capacity_min', $service->getCapacityMin() )
                        ->set( 'capacity_max', $service->getCapacityMax() )
                        ->where( 'service_id', self::parameter( 'id' ) )
                        ->execute();
                }
                // Create records for newly linked staff.
                $existing_staff_ids = array();
                $res                = Lib\Entities\StaffService::query()
                    ->select( 'staff_id' )
                    ->where( 'service_id', $service->getId() )
                    ->fetchArray();
                foreach ( $res as $staff ) {
                    $existing_staff_ids[] = $staff['staff_id'];
                }
                foreach ( $staff_ids as $staff_id ) {
                    if ( ! in_array( $staff_id, $existing_staff_ids ) ) {
                        $staff_service = new Lib\Entities\StaffService();
                        $staff_service->setStaffId( $staff_id )
                            ->setServiceId( $service->getId() )
                            ->setPrice( $service->getPrice() )
                            ->setCapacityMin( $service->getCapacityMin() )
                            ->setCapacityMax( $service->getCapacityMax() )
                            ->save();
                    }
                }
            }
        }

        // Update services in addons.
        $alert = Proxy\Shared::updateService( array( 'success' => array( __( 'Settings saved.', 'bookly' ) ) ), $service, self::postParameters() );

        wp_send_json_success( Proxy\Shared::prepareUpdateServiceResponse( compact( 'alert' ), $service, self::postParameters() ) );
    }
}