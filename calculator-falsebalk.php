<?php
/**
 * Калькулятор: Фальшбалки (Falsebalk Calculator)
 * Описание: Калькулятор для фальшбалок с выбором формы сечения
 * Категория: 266
 * Зависимости: category-helpers, product-calculations
 */

if (!defined('ABSPATH')) {
    exit;
}

function display_falsebalk_calculator($product_id, $price) {
    // Получаем данные о формах сечения
    $shapes_data = get_post_meta($product_id, '_falsebalk_shapes_data', true);
    
    if (!is_array($shapes_data) || empty($shapes_data)) {
        return;
    }
    
    $base_price = $price;
    
    // Названия форм
    $shape_names = array(
        'g' => 'Г-образная',
        'p' => 'П-образная',
        'o' => 'О-образная'
    );
    
    // Иконки SVG для форм
    $shape_icons = array(
        'g' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="10" height="50" fill="#000"/><rect x="5" y="45" width="50" height="10" fill="#000"/></svg>',
        'p' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="10" height="50" fill="#000"/><rect x="45" y="5" width="10" height="50" fill="#000"/><rect x="5" y="5" width="50" height="10" fill="#000"/></svg>',
        'o' => '<svg width="60" height="60" viewBox="0 0 60 60"><rect x="5" y="5" width="50" height="50" fill="none" stroke="#000" stroke-width="10"/></svg>'
    );
    
    ?>
    <div id="falsebalk-calculator" class="parusweb-calculator" style="margin: 20px 0; padding: 20px; border: 2px solid #ddd; border-radius: 8px; background: #f9f9f9;">
        <h4 style="margin-top: 0;">Калькулятор фальшбалок</h4>
        
        <div style="margin-bottom: 20px;">
            <strong style="display: block; margin-bottom: 10px;">Выберите форму сечения:</strong>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <?php foreach ($shapes_data as $shape_key => $shape_info): 
                    if (is_array($shape_info) && !empty($shape_info['enabled'])): 
                        $shape_label = isset($shape_names[$shape_key]) ? $shape_names[$shape_key] : ucfirst($shape_key);
                        ?>
                        <label class="shape-tile" data-shape="<?php echo esc_attr($shape_key); ?>" 
                               style="cursor: pointer; border: 2px solid #ddd; border-radius: 10px; padding: 10px; background: #fff; display: flex; flex-direction: column; align-items: center; gap: 8px; transition: all .2s; min-width: 100px;">
                            <input type="radio" name="falsebalk_shape" value="<?php echo esc_attr($shape_key); ?>" style="display: none;">
                            <div><?php echo $shape_icons[$shape_key]; ?></div>
                            <span style="font-size: 12px; color: #666; text-align: center;"><?php echo esc_html($shape_label); ?></span>
                        </label>
                    <?php endif; 
                endforeach; ?>
            </div>
        </div>
        
        <div id="falsebalk_dimensions" style="display: none;">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Ширина (мм):</label>
                <select id="fb_width" name="custom_rm_width" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                    <option value="">Выберите...</option>
                </select>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Высота (мм):</label>
                <select id="fb_height" name="custom_rm_height" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                    <option value="">Выберите...</option>
                </select>
            </div>
            
            <div id="fb_height2_container" style="margin-bottom: 15px; display: none;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Высота 2 (мм):</label>
                <select id="fb_height2" name="custom_rm_height2" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                    <option value="">Выберите...</option>
                </select>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Длина (м):</label>
                <input type="number" id="fb_length" name="custom_rm_length" 
                       min="0.1" step="0.1" value="" placeholder="0.0"
                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
            </div>
            
            <div id="fb_calc_result" style="padding: 15px; background: #fff; border: 2px solid #ddd; border-radius: 6px; display: none; margin-top: 15px;"></div>
        </div>
        
        <!-- Скрытые поля -->
        <input type="hidden" id="fb_base_price" value="<?php echo esc_attr($base_price); ?>">
    </div>
    
    <script>
    (function() {
        const shapesData = <?php echo json_encode($shapes_data); ?>;
        const basePrice = parseFloat(document.getElementById('fb_base_price').value) || 0;
        const formMultipliers = { 'g': 2, 'p': 3, 'o': 4 };
        const shapeNames = <?php echo json_encode($shape_names); ?>;
        
        let currentShape = null;
        const shapeTiles = document.querySelectorAll('.shape-tile');
        const dimensionsBlock = document.getElementById('falsebalk_dimensions');
        const widthSelect = document.getElementById('fb_width');
        const heightSelect = document.getElementById('fb_height');
        const height2Container = document.getElementById('fb_height2_container');
        const height2Select = document.getElementById('fb_height2');
        const lengthInput = document.getElementById('fb_length');
        const resultBlock = document.getElementById('fb_calc_result');
        const quantityInput = document.querySelector('input.qty');
        
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
            dimensionsBlock.appendChild(input);
        }
        
        // Выбор формы
        shapeTiles.forEach(tile => {
            tile.addEventListener('click', function() {
                shapeTiles.forEach(t => {
                    t.style.borderColor = '#ddd';
                    t.style.background = '#fff';
                });
                
                this.style.borderColor = '#2271b1';
                this.style.background = '#e8f4f8';
                this.querySelector('input[type="radio"]').checked = true;
                
                currentShape = this.dataset.shape;
                loadShapeData(currentShape);
                dimensionsBlock.style.display = 'block';
                resultBlock.style.display = 'none';
                
                createHiddenField('custom_rm_shape', currentShape);
                createHiddenField('falsebalk_shape_label', shapeNames[currentShape]);
            });
        });
        
        function loadShapeData(shape) {
            const data = shapesData[shape];
            if (!data) return;
            
            // Загружаем ширину
            widthSelect.innerHTML = '<option value="">Выберите...</option>';
            if (data.widths && data.widths.length) {
                data.widths.forEach(w => {
                    const option = document.createElement('option');
                    option.value = w;
                    option.textContent = w + ' мм';
                    widthSelect.appendChild(option);
                });
            }
            
            // Загружаем высоту
            heightSelect.innerHTML = '<option value="">Выберите...</option>';
            if (data.heights && data.heights.length) {
                data.heights.forEach(h => {
                    const option = document.createElement('option');
                    option.value = h;
                    option.textContent = h + ' мм';
                    heightSelect.appendChild(option);
                });
            }
            
            // Высота 2 только для П-образных
            if (shape === 'p' && data.heights2 && data.heights2.length) {
                height2Container.style.display = 'block';
                height2Select.innerHTML = '<option value="">Выберите...</option>';
                data.heights2.forEach(h2 => {
                    const option = document.createElement('option');
                    option.value = h2;
                    option.textContent = h2 + ' мм';
                    height2Select.appendChild(option);
                });
            } else {
                height2Container.style.display = 'none';
            }
        }
        
        function calculateFalsebalk() {
            const width = parseFloat(widthSelect.value) || 0;
            const height = parseFloat(heightSelect.value) || 0;
            const height2 = parseFloat(height2Select.value) || 0;
            const length = parseFloat(lengthInput.value) || 0;
            
            if (width <= 0 || height <= 0 || length <= 0 || !currentShape) {
                resultBlock.style.display = 'none';
                return;
            }
            
            // П-образная требует обе высоты
            if (currentShape === 'p' && height2 <= 0) {
                resultBlock.style.display = 'none';
                return;
            }
            
            const formMult = formMultipliers[currentShape];
            
            // Рассчитываем площадь покраски
            let paintArea = 0;
            if (currentShape === 'g') {
                paintArea = ((width + height) / 1000) * length;
            } else if (currentShape === 'p') {
                paintArea = ((width + height + height2) / 1000) * length;
            } else if (currentShape === 'o') {
                paintArea = ((width * 2 + height * 2) / 1000) * length;
            }
            
            const pricePerMeter = (width / 1000) * basePrice * formMult;
            const totalPrice = pricePerMeter * length;
            
            // Получаем стоимость покраски
            let paintingPrice = 0;
            const paintingSelect = document.getElementById('painting_service_select');
            if (paintingSelect && paintingSelect.value) {
                const paintingOption = paintingSelect.options[paintingSelect.selectedIndex];
                const paintingPricePerM2 = parseFloat(paintingOption.dataset.price) || 0;
                paintingPrice = paintArea * paintingPricePerM2;
            }
            
            const grandTotal = totalPrice + paintingPrice;
            
            // Обновляем вывод
            let html = 'Площадь покраски: <b>' + paintArea.toFixed(2) + ' м²</b><br>';
            html += 'Цена за м.п.: <b>' + Math.round(pricePerMeter) + ' ₽</b><br>';
            html += 'Стоимость материала: <b>' + Math.round(totalPrice) + ' ₽</b><br>';
            if (paintingPrice > 0) {
                html += 'Стоимость покраски: <b>' + Math.round(paintingPrice) + ' ₽</b><br>';
            }
            html += '<strong style="font-size: 1.2em;">Итого: <b>' + Math.round(grandTotal) + ' ₽</b></strong>';
            
            resultBlock.innerHTML = html;
            resultBlock.style.display = 'block';
            
            // Обновляем скрытые поля
            createHiddenField('custom_rm_width', width);
            createHiddenField('custom_rm_height', height);
            if (currentShape === 'p') {
                createHiddenField('custom_rm_height2', height2);
            }
            createHiddenField('custom_rm_length', length);
            createHiddenField('custom_rm_price', totalPrice.toFixed(2));
            createHiddenField('custom_rm_grand_total', grandTotal.toFixed(2));
            createHiddenField('custom_rm_painting_area', paintArea.toFixed(2));
            createHiddenField('custom_rm_multiplier', formMult);
            createHiddenField('custom_rm_quantity', 1);
            createHiddenField('custom_rm_total_length', length);
            
            // Обновляем количество WooCommerce
            if (quantityInput) {
                quantityInput.value = 1;
                quantityInput.readOnly = true;
                quantityInput.style.backgroundColor = '#f5f5f5';
            }
        }
        
        widthSelect.addEventListener('change', calculateFalsebalk);
        heightSelect.addEventListener('change', calculateFalsebalk);
        height2Select.addEventListener('change', calculateFalsebalk);
        lengthInput.addEventListener('input', calculateFalsebalk);
        
        // Обработчик покраски
        const paintingSelect = document.getElementById('painting_service_select');
        if (paintingSelect) {
            paintingSelect.addEventListener('change', calculateFalsebalk);
        }
        
        console.log('✓ Falsebalk Calculator initialized');
    })();
    </script>
    <?php
}