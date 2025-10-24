
<?php
/**
 * Модуль: Главный диспетчер калькуляторов
 * Назначение: Определяет тип калькулятора для товара и выводит его
 * Структура:
 * 1. Калькуляторы (разные типы)
 * 2. Фаска (для категорий 266 и 267)
 * 3. Услуги покраски (после калькулятора, вне формы)
 */

if (!defined('ABSPATH')) {
    exit;
}

// === Главная функция диспетчера калькуляators ===
add_action('woocommerce_before_add_to_cart_button', 'pm_display_product_calculators', 10);

function pm_display_product_calculators() {
    global $product;
    if (!$product) return;

    $product_id = $product->get_id();

    // Проверка категорий, нужен ли калькулятор
    if (!function_exists('is_in_target_categories') || !is_in_target_categories($product_id)) {
        return;
    }

    $calculator_type = function_exists('get_calculator_type') ? get_calculator_type($product_id) : 'none';
    if ($calculator_type === 'none') return;

    $base_price = $product->get_price();
    $title = $product->get_title();
    $area_data = function_exists('extract_area_with_qty') ? extract_area_with_qty($title, $product_id) : null;
    $multiplier = function_exists('get_final_multiplier') ? get_final_multiplier($product_id) : 1.0;
    $price_with_multiplier = $base_price * $multiplier;

    echo '<div class="product-calculators-wrapper" style="margin: 20px 0;">';
    
    // === Калькуляторы ===
    switch ($calculator_type) {
        case 'area':
            if (function_exists('display_area_calculator')) {
                display_area_calculator($product_id, $base_price);
            }
            break;

        case 'square_meter':
            if (function_exists('display_square_meter_calculator')) {
                display_square_meter_calculator($product_id, $price_with_multiplier, $area_data);
            }
            break;

        case 'running_meter':
            if (function_exists('display_running_meter_calculator')) {
                display_running_meter_calculator($product_id, $price_with_multiplier);
            }
            break;

        case 'falsebalk':
            if (function_exists('display_falsebalk_calculator')) {
                display_falsebalk_calculator($product_id, $price_with_multiplier);
            }
            break;

        case 'dimensions':
            if (function_exists('display_dimensions_calculator')) {
                display_dimensions_calculator($product_id, $price_with_multiplier, $area_data);
            }
            break;
    }

    // === Фаска (только для категорий 266 и 267) ===
    $faska_categories = [266, 267];
    $product_cats = wc_get_product_term_ids($product_id, 'product_cat');
    if (array_intersect($faska_categories, $product_cats) && function_exists('display_faska_selector')) {
        display_faska_selector($product_id);
    }

    echo '</div>'; // закрываем .product-calculators-wrapper
}

// === Услуги покраски (после калькулятора, ВНЕ формы) ===
add_action('woocommerce_after_single_product_summary', 'pm_display_painting_services_wrapper', 5);

function pm_display_painting_services_wrapper() {
    global $product;
    if (!$product) return;

    $product_id = $product->get_id();
    
    // Проверка категорий, нужен ли калькулятор
    if (!function_exists('is_in_target_categories') || !is_in_target_categories($product_id)) {
        return;
    }

    pm_display_painting_services($product_id);
}

// === Блок покраски вне формы ===
function pm_display_painting_services($product_id) {
    if (!function_exists('get_available_painting_services_by_material')) return;

    $services = get_available_painting_services_by_material($product_id);
    if (empty($services)) return;

    // Определяем, является ли товар столярным изделием
    $is_carpentry = false;
    $carpentry_categories = [265, 266, 267, 268, 269, 270, 271];
    $product_cats = wc_get_product_term_ids($product_id, 'product_cat');
    if (array_intersect($carpentry_categories, $product_cats)) {
        $is_carpentry = true;
    }

    ?>
    <div class="painting-services-wrapper" style="margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9; display: none;">
        <div style="margin-bottom: 10px;">
            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Услуги покраски</label>
            <select id="painting_service_select" name="painting_service_key"
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                <option value="">Без покраски</option>
                <?php foreach ($services as $key => $service): ?>
                    <option value="<?php echo esc_attr($key); ?>"
                            data-price="<?php echo esc_attr($service['price']); ?>"
                            data-schemes='<?php echo esc_attr(json_encode($service['schemes'] ?? [])); ?>'>
                        <?php echo esc_html($service['name']); ?>
                        <?php if (!$is_carpentry): ?>
                            (+<?php echo wc_price($service['price']); ?> за м²)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="painting_color_container" style="display: none; margin-top: 10px;">
            <label style="display: block; font-weight: 600; margin-bottom: 5px;">Выберите цвет</label>
            <select id="painting_color_select" name="painting_color_id"
                    style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px;">
                <option value="">Выберите цвет...</option>
            </select>
        </div>
    </div>

    <script>
    jQuery(function($){
        // Показываем блок покраски только когда есть расчеты
        $(document).on('calculator_updated', function() {
            const paintingWrapper = $('.painting-services-wrapper');
            if ($('#area_input').val() || $('.calc-result-container').is(':visible')) {
                paintingWrapper.show();
            }
        });

        $('#painting_service_select').on('change', function(){
            var selectedOption = $(this).find('option:selected');
            var schemes = selectedOption.data('schemes') || [];
            
            if(schemes.length > 0){
                $('#painting_color_select').html('<option value="">Выберите цвет...</option>');
                schemes.forEach(function(s){ 
                    $('#painting_color_select').append('<option value="'+s.id+'">'+s.name+'</option>'); 
                });
                $('#painting_color_container').show();
            } else { 
                $('#painting_color_container').hide(); 
            }
            
            $(document).trigger('painting_service_changed');
        });
        
        $('#painting_color_select').on('change', function(){ 
            $(document).trigger('painting_service_changed');
        });
    });
    </script>
    <?php
}
