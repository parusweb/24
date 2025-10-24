<?php
/**
 * Калькулятор: Площадь (Area Calculator)
 * Описание: Калькулятор для товаров с площадью в названии (пиломатериалы, листовые)
 * Категории: 87-93 (пиломатериалы), 190-191, 127, 94 (листовые)
 * Зависимости: category-helpers, product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

function display_area_calculator($product_id, $price) {
    $product = wc_get_product($product_id);
    if (!$product) {
        return;
    }
    
    $title = $product->get_title();
    $area_data = function_exists('extract_area_with_qty') ? extract_area_with_qty($title, $product_id) : null;
    
    if (!$area_data || $area_data <= 0) {
        return;
    }
    
    $is_leaf = function_exists('is_leaf_category') ? is_leaf_category($product_id) : false;
    $unit_text = $is_leaf ? 'листа' : 'упаковки';
    $unit_text_plural = $is_leaf ? 'листов' : 'упаковок';
    $unit_nominative = $is_leaf ? 'лист' : 'упаковка';
    
    ?>
    <div id="area-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0;">Расчет количества по площади</h4>
        
        <div style="margin-bottom: 10px;">
            Площадь одного <?php echo $unit_text; ?>: <strong><?php echo number_format($area_data, 3, ',', ' '); ?> м²</strong><br>
            Цена за <?php echo $unit_nominative; ?>: <strong><?php echo wc_price($price * $area_data); ?></strong>
        </div>
        
        <div style="margin-bottom: 15px;">
            <label>Введите нужную площадь, м²:
                <input type="number" id="area_input" name="area_input" 
                       min="<?php echo $area_data; ?>" step="0.1" placeholder="1"
                       style="width: 100px; margin-left: 10px; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
            </label>
        </div>
        
        <div id="area_calc_result" style="margin-top: 10px; font-size: 1.3em;"></div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="area_base_price" value="<?php echo esc_attr($price); ?>">
        <input type="hidden" id="area_pack_area" value="<?php echo esc_attr($area_data); ?>">
        <?php do_action('parusweb_after_calculators', $product_id); ?>
    </div>
    
    <script>
    (function() {
        const areaInput = document.getElementById('area_input');
        const areaResult = document.getElementById('area_calc_result');
        const basePriceM2 = parseFloat(document.getElementById('area_base_price').value) || 0;
        const packArea = parseFloat(document.getElementById('area_pack_area').value) || 0;
        const unitForms = <?php echo json_encode(array($unit_nominative, $unit_nominative === 'лист' ? 'листа' : 'упаковки', $unit_text_plural)); ?>;
        
        let isAutoUpdate = false;
        
        // Функция для получения поля количества WooCommerce
        function getQuantityInput() {
            const selectors = [
                'input.qty',
                '.quantity input',
                'input[name="quantity"]',
                'form.cart input[type="number"]'
            ];
            
            for (let selector of selectors) {
                const input = document.querySelector(selector);
                if (input && input.type === 'number') {
                    return input;
                }
            }
            return null;
        }
        
        function getRussianPlural(n, forms) {
            n = Math.abs(n) % 100;
            const n1 = n % 10;
            if (n > 10 && n < 20) return forms[2];
            if (n1 > 1 && n1 < 5) return forms[1];
            if (n1 === 1) return forms[0];
            return forms[2];
        }
        
        function removeHiddenFields(prefix) {
            const fields = document.querySelectorAll('input[name^="' + prefix + '"]');
            fields.forEach(field => field.remove());
        }
        
        function createHiddenField(name, value) {
            removeHiddenFields(name);
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            areaInput.parentElement.appendChild(input);
        }
        
        function getPaintingServiceCost(totalArea) {
            const serviceSelect = document.getElementById('painting_service_select');
            if (!serviceSelect || !serviceSelect.value) return 0;
            
            const serviceOption = serviceSelect.options[serviceSelect.selectedIndex];
            const servicePrice = parseFloat(serviceOption.dataset.price) || 0;
            return totalArea * servicePrice;
        }
        
        function updateQuantityField(packs) {
            const quantityInput = getQuantityInput();
            if (quantityInput) {
                isAutoUpdate = true;
                quantityInput.value = packs;
                
                // Триггерим события для WooCommerce
                quantityInput.dispatchEvent(new Event('input', { bubbles: true }));
                quantityInput.dispatchEvent(new Event('change', { bubbles: true }));
                
                // Также триггерим события для jQuery
                if (typeof jQuery !== 'undefined') {
                    jQuery(quantityInput).trigger('input').trigger('change');
                }
                
                setTimeout(() => { isAutoUpdate = false; }, 100);
            }
        }
        
        function updateAreaCalc() {
            const area = parseFloat(areaInput.value);
            
            if (!area || area <= 0) {
                areaResult.innerHTML = '';
                removeHiddenFields('custom_area_');
                
                // Скрываем блок покраски если нет расчетов
                const paintingWrapper = document.querySelector('.painting-services-wrapper');
                if (paintingWrapper) paintingWrapper.style.display = 'none';
                
                // Сбрасываем количество
                updateQuantityField(1);
                return;
            }
            
            const packs = Math.ceil(area / packArea);
            const materialPrice = packs * basePriceM2 * packArea;
            const totalArea = packs * packArea;
            const paintingCost = getPaintingServiceCost(totalArea);
            const grandTotal = materialPrice + paintingCost;
            
            const plural = getRussianPlural(packs, unitForms);
            
            let html = 'Нужная площадь: <b>' + area.toFixed(2) + ' м²</b><br>';
            html += 'Необходимо: <b>' + packs + ' ' + plural + '</b><br>';
            html += 'Стоимость материала: <b>' + materialPrice.toFixed(2) + ' ₽</b><br>';
            
            // Всегда показываем стоимость покраски если она есть
            if (paintingCost > 0) {
                html += 'Стоимость покраски: <b>' + paintingCost.toFixed(2) + ' ₽</b><br>';
                html += '<strong>Итого с покраской: <b>' + grandTotal.toFixed(2) + ' ₽</b></strong>';
            } else {
                html += '<strong>Итого: <b>' + materialPrice.toFixed(2) + ' ₽</b></strong>';
            }
            
            areaResult.innerHTML = html;
            
            createHiddenField('custom_area_packs', packs);
            createHiddenField('custom_area_area_value', area.toFixed(2));
            createHiddenField('custom_area_total_price', materialPrice.toFixed(2));
            createHiddenField('custom_area_painting_cost', paintingCost.toFixed(2));
            createHiddenField('custom_area_grand_total', grandTotal.toFixed(2));
            
            // Обновляем поле количества
            updateQuantityField(packs);
            
            // Показываем блок покраски когда есть расчеты
            const paintingWrapper = document.querySelector('.painting-services-wrapper');
            if (paintingWrapper) paintingWrapper.style.display = 'block';
            
            // Триггерим событие обновления калькулятора
            if (typeof jQuery !== 'undefined') {
                jQuery(document).trigger('calculator_updated');
            }
        }
        
        function syncFromQuantityField() {
            if (isAutoUpdate) return;
            
            const quantityInput = getQuantityInput();
            if (!quantityInput) return;
            
            const packs = parseInt(quantityInput.value);
            if (packs > 0) {
                const area = packs * packArea;
                areaInput.value = area.toFixed(2);
                updateAreaCalc();
            }
        }
        
        // Инициализация событий
        areaInput.addEventListener('input', updateAreaCalc);
        
        // Синхронизация с полем количества WooCommerce
        const quantityInput = getQuantityInput();
        if (quantityInput) {
            quantityInput.addEventListener('input', function() {
                if (!isAutoUpdate) {
                    syncFromQuantityField();
                }
            });
            
            quantityInput.addEventListener('change', function() {
                if (!isAutoUpdate) {
                    syncFromQuantityField();
                }
            });
            
            if (typeof jQuery !== 'undefined') {
                jQuery(quantityInput).on('input change', function() {
                    if (!isAutoUpdate) {
                        syncFromQuantityField();
                    }
                });
            }
        }
        
        // Обработчик изменения услуг покраски
        const paintingSelect = document.getElementById('painting_service_select');
        if (paintingSelect) {
            paintingSelect.addEventListener('change', function() {
                if (areaInput.value) {
                    updateAreaCalc();
                }
            });
        }
        
        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            if (!areaInput.value) {
                areaInput.value = packArea.toFixed(2);
                updateAreaCalc();
            }
        });
        
        console.log('✓ Area Calculator initialized');
    })();
    </script>
    <?php
}
