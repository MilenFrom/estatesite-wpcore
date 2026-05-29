<?php
/**
 * Auto-ported from Houzez framework/functions/ to EstateSite Core.
 * Direct fave_* meta access has been rewritten to use \EstateSite\Core\Property::get/set.
 *
 * @package EstateSite\Core\Functions
 */

defined( 'ABSPATH' ) || exit;

/**
 * Property Schema Markup Generator
 * Generates JSON-LD schema for property listings with multi-language support
 *
 * @package Houzez
 * @since 4.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get property schema type mapping
 * Maps Houzez property types to Schema.org types
 *
 * @param string $property_type_slug The property type slug
 * @return string Schema.org type
 */
function houzez_get_property_schema_type( $property_type_slug = '' ) {

    // Default schema type mapping
    $type_mapping = array(
        'apartment'             => 'Apartment',
        'condo'                 => 'Apartment',
        'condominium'           => 'Apartment',
        'penthouse'             => 'Apartment',
        'studio'                => 'Apartment',
        'house'                 => 'SingleFamilyResidence',
        'single-family-home'    => 'SingleFamilyResidence',
        'townhouse'             => 'SingleFamilyResidence',
        'town-house'            => 'SingleFamilyResidence',
        'villa'                 => 'House',
        'cottage'               => 'House',
        'bungalow'              => 'House',
        'land'                  => 'LandParcel',
        'lot'                   => 'LandParcel',
        'plot'                  => 'LandParcel',
    );

    /**
     * Filter: Allow developers to customize property type to schema type mapping
     *
     * @param array  $type_mapping      Associative array of property_type_slug => schema_type
     * @param string $property_type_slug Current property type slug
     *
     * Example usage:
     * add_filter( 'houzez_property_schema_type_mapping', function( $mapping, $type_slug ) {
     *     $mapping['warehouse'] = 'Accommodation';
     *     $mapping['farmhouse'] = 'House';
     *     return $mapping;
     * }, 10, 2 );
     */
    $type_mapping = apply_filters( 'houzez_property_schema_type_mapping', $type_mapping, $property_type_slug );

    // Return mapped type or fallback to 'RealEstateListing' (better for real estate SEO)
    return isset( $type_mapping[ $property_type_slug ] ) ? $type_mapping[ $property_type_slug ] : 'RealEstateListing';
}

/**
 * Generate property schema markup
 *
 * @param int $property_id Property post ID
 * @return array|false Schema data array or false on failure
 */
function houzez_get_property_schema( $property_id = 0 ) {

    if ( empty( $property_id ) ) {
        $property_id = get_the_ID();
    }

    // Verify it's a property post type
    if ( get_post_type( $property_id ) !== 'property' ) {
        return false;
    }

    global $post;
    $property_post = get_post( $property_id );

    // Get property type for schema type determination
    $property_types = get_the_terms( $property_id, 'property_type' );
    $property_type_slug = '';
    if ( $property_types && ! is_wp_error( $property_types ) ) {
        $property_type_slug = $property_types[0]->slug;
    }

    // Determine schema type
    $schema_type = houzez_get_property_schema_type( $property_type_slug );

    // Initialize schema
    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => $schema_type,
    );

    // Language support (WPML/Polylang)
    if ( function_exists( 'icl_get_current_language' ) ) {
        $current_lang = icl_get_current_language();
        $schema['inLanguage'] = $current_lang;
    } elseif ( function_exists( 'pll_current_language' ) ) {
        $current_lang = pll_current_language();
        $schema['inLanguage'] = $current_lang;
    } else {
        $schema['inLanguage'] = get_bloginfo( 'language' );
    }

    // Property name/title
    $schema['name'] = get_the_title( $property_id );

    // Property description
    if ( ! empty( $property_post->post_content ) ) {
        $schema['description'] = wp_strip_all_tags( $property_post->post_content );
    }

    // Property URL
    $schema['url'] = get_permalink( $property_id );

    // Property images
    $images = array();

    // Featured image
    $featured_image = get_the_post_thumbnail_url( $property_id, 'full' );
    if ( $featured_image ) {
        $images[] = $featured_image;
    }

    // Gallery images
    $gallery_images = get_post_meta( $property_id, \EstateSite\Core\Property::key( 'images' ), false );
    if ( ! empty( $gallery_images ) && is_array( $gallery_images ) ) {
        foreach ( $gallery_images as $image_id ) {
            $image_url = wp_get_attachment_url( $image_id );
            if ( $image_url ) {
                $images[] = $image_url;
            }
        }
    }

    if ( ! empty( $images ) ) {
        // Remove duplicates (e.g., featured image may also be in gallery)
        $schema['image'] = array_values( array_unique( $images ) );
    }

    // Number of bedrooms
    $bedrooms = \EstateSite\Core\Property::get( $property_id, 'bedrooms' );
    if ( ! empty( $bedrooms ) && is_numeric( $bedrooms ) ) {
        $schema['numberOfBedrooms'] = (int) $bedrooms;
    }

    // Number of bathrooms
    $bathrooms = \EstateSite\Core\Property::get( $property_id, 'bathrooms' );
    if ( ! empty( $bathrooms ) && is_numeric( $bathrooms ) ) {
        $schema['numberOfBathroomsTotal'] = (float) $bathrooms;
    }

    // Floor size
    $property_size = \EstateSite\Core\Property::get( $property_id, 'size' );
    $size_prefix = \EstateSite\Core\Property::get( $property_id, 'size_prefix' );

    if ( ! empty( $property_size ) && is_numeric( $property_size ) ) {
        // Determine unit text based on global settings or property-level prefix
        $unit_text = 'SQFT'; // Default

        if ( houzez_option( 'measurement_unit_global', 0 ) ) {
            // Use global measurement unit setting
            $measurement_unit = houzez_option( 'measurement_unit', 'sqft' );
            $unit_text = ( $measurement_unit === 'sq_meter' ) ? 'SQM' : 'SQFT';
        } elseif ( ! empty( $size_prefix ) ) {
            // Map common variations to schema-standard units
            $prefix_upper = strtoupper( trim( $size_prefix ) );
            if ( in_array( $prefix_upper, array( 'M2', 'M²', 'SQ M', 'SQM', 'SQUARE METER', 'SQUARE METERS', 'SQ. M', 'SQ.M' ), true ) ) {
                $unit_text = 'SQM';
            } elseif ( in_array( $prefix_upper, array( 'FT2', 'FT²', 'SQ FT', 'SQFT', 'SQUARE FEET', 'SQUARE FOOT', 'SQ. FT', 'SQ.FT' ), true ) ) {
                $unit_text = 'SQFT';
            } else {
                // Use the provided prefix as-is if it doesn't match known patterns
                $unit_text = $prefix_upper;
            }
        }

        $schema['floorSize'] = array(
            '@type'    => 'QuantitativeValue',
            'value'    => (float) $property_size,
            'unitText' => $unit_text,
        );
    }

    // Year built
    $year_built = \EstateSite\Core\Property::get( $property_id, 'year' );
    if ( ! empty( $year_built ) && is_numeric( $year_built ) ) {
        $schema['yearBuilt'] = (int) $year_built;
    }

    // Property address
    $address = array(
        '@type' => 'PostalAddress',
    );

    $street_address = \EstateSite\Core\Property::get( $property_id, 'address' );
    if ( ! empty( $street_address ) ) {
        $address['streetAddress'] = $street_address;
    }

    $city_terms = get_the_terms( $property_id, 'property_city' );
    if ( $city_terms && ! is_wp_error( $city_terms ) ) {
        $address['addressLocality'] = $city_terms[0]->name;
    }

    $state_terms = get_the_terms( $property_id, 'property_state' );
    if ( $state_terms && ! is_wp_error( $state_terms ) ) {
        $address['addressRegion'] = $state_terms[0]->name;
    }

    $zip = \EstateSite\Core\Property::get( $property_id, 'zip' );
    if ( ! empty( $zip ) ) {
        $address['postalCode'] = $zip;
    }

    $country_terms = get_the_terms( $property_id, 'property_country' );
    if ( $country_terms && ! is_wp_error( $country_terms ) ) {
        $address['addressCountry'] = $country_terms[0]->name;
    }

    // Only add address if at least one field is present
    if ( count( $address ) > 1 ) {
        $schema['address'] = $address;
    }

    // Geo coordinates (rounded to 6 decimal places for cleaner schema output)
    $lat = get_post_meta( $property_id, 'houzez_geolocation_lat', true );
    $lng = get_post_meta( $property_id, 'houzez_geolocation_long', true );

    if ( ! empty( $lat ) && ! empty( $lng ) ) {
        $schema['geo'] = array(
            '@type'     => 'GeoCoordinates',
            'latitude'  => round( (float) $lat, 6 ),
            'longitude' => round( (float) $lng, 6 ),
        );
    }

    // Property price and offer
    $price = \EstateSite\Core\Property::get( $property_id, 'price' );

    if ( ! empty( $price ) && is_numeric( $price ) ) {

        // Get currency from schema-specific option (allows explicit control)
        $currency = houzez_option( 'schema_currency', 'USD' );
        if ( empty( $currency ) ) {
            $currency = 'USD';
        }

        // Get agent/agency information using proper Houzez function
        // This respects the fave_agent_display_option setting
        $agent_data = function_exists( 'houzez20_get_property_agent' ) ? houzez20_get_property_agent() : array();

        $seller = array(
            '@type' => 'RealEstateAgent',
            'name'  => get_bloginfo( 'name' ),
        );

        if ( ! empty( $agent_data ) && ! empty( $agent_data['agent_type'] ) && $agent_data['agent_type'] !== 'none' ) {

            // Determine schema type based on agent type
            if ( $agent_data['agent_type'] === 'agency_info' ) {
                $seller['@type'] = 'Organization';
            } else {
                // agent_info or author_info = RealEstateAgent
                $seller['@type'] = 'RealEstateAgent';
            }

            // Add agent/agency name
            if ( ! empty( $agent_data['agent_name'] ) ) {
                $seller['name'] = $agent_data['agent_name'];
            }

            // Add URL
            if ( ! empty( $agent_data['link'] ) ) {
                $seller['url'] = $agent_data['link'];
            }

            // Add phone number (prefer mobile, fallback to phone)
            $phone = '';
            if ( ! empty( $agent_data['agent_mobile'] ) ) {
                $phone = $agent_data['agent_mobile'];
            } elseif ( ! empty( $agent_data['agent_phone'] ) ) {
                $phone = $agent_data['agent_phone'];
            }
            if ( ! empty( $phone ) ) {
                $seller['telephone'] = $phone;
            }

            // Add email (only if explicitly enabled in theme options for privacy)
            if ( houzez_option( 'schema_show_agent_email', 0 ) && ! empty( $agent_data['agent_email'] ) ) {
                $seller['email'] = $agent_data['agent_email'];
            }

            if ( ! empty( $agent_data['agent_address'] ) ) {
                $seller['address'] = $agent_data['agent_address'];
            }
        }

        // Determine availability
        $property_status = get_post_status( $property_id );
        $availability = 'https://schema.org/InStock';

        if ( $property_status === 'expired' ) {
            $availability = 'https://schema.org/OutOfStock';
        }

        // Price valid until (set to end of year or custom)
        $price_valid_until = date( 'Y' ) . '-12-31';

        $offer = array(
            '@type'           => 'Offer',
            'url'             => get_permalink( $property_id ),
            'priceCurrency'   => $currency,
            'price'           => (float) $price,
            'priceValidUntil' => $price_valid_until,
            'availability'    => $availability,
            'seller'          => $seller,
        );

        $schema['offers'] = $offer;
    }

    // Property ratings (if reviews are enabled)
    if ( function_exists( 'houzez_reviews_enabled' ) && houzez_reviews_enabled() ) {

        $property_rating = get_post_meta( $property_id, 'prop_average_rating', true );
        $property_rating_count = get_post_meta( $property_id, 'prop_total_rating', true );

        if ( ! empty( $property_rating ) && ! empty( $property_rating_count ) && $property_rating_count > 0 ) {
            $schema['aggregateRating'] = array(
                '@type'       => 'AggregateRating',
                'ratingValue' => (float) $property_rating,
                'reviewCount' => (int) $property_rating_count,
                'bestRating'  => 5,
                'worstRating' => 1,
            );
        }
    }

    /**
     * Filter: Allow developers to modify the complete schema output
     *
     * @param array $schema      Complete schema array
     * @param int   $property_id Property post ID
     */
    $schema = apply_filters( 'houzez_property_schema', $schema, $property_id );

    return $schema;
}

/**
 * Output property schema in wp_head
 */
function houzez_output_property_schema() {

    // Check if schema markup is enabled in theme options
    if ( ! houzez_option( 'enable_property_schema', 1 ) ) {
        return;
    }

    // Only output on single property pages
    if ( ! is_singular( 'property' ) ) {
        return;
    }

    $schema = houzez_get_property_schema();

    if ( empty( $schema ) ) {
        return;
    }

    // Output JSON-LD
    echo '<script type="application/ld+json">';
    echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    echo '</script>' . "\n";
}
add_action( 'wp_head', 'houzez_output_property_schema', 5 );
