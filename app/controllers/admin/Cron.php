<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Cron extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->lang->admin_load('cron');
        $this->load->admin_model('cron_model');
        $this->load->admin_model('products_model');
        $this->Settings = $this->cron_model->getSettings();
    }

    public function index()
    {
        $products = json_decode($this->sendRequest());
        foreach ($products as $product) {
            $category             = $this->site->getCategoryByName($product->categoria->nome);
            if(!$category){
                $this->db->insert('categories', ['name'=> $product->categoria->nome]);
                $category         = $this->site->getCategoryByName($product->categoria->nome);
            }

            $unity                = $this->site->getUnityByName($product->unidade_compra);
            if(!$unity){
                $this->db->insert('units', ['name'=> $product->unidade_compra, 'code'=> $product->unidade_compra]);
                $unity            = $this->site->getCategoryByName($product->unidade_compra);
            }

            $findProduct          = $this->site->getProductByExternalReference($product->id);
            $random = substr(md5(mt_rand()), 0, 28);

            if($findProduct) continue;
            $tax_rate = $this->input->post('tax_rate') ? $this->site->getTaxRateByID($this->input->post('tax_rate')) : null;
            
            $codBarras = $product->codBarras;
            if($product->codBarras == 'SEM GTIN'){
                $codBarras = substr(md5(mt_rand()), 0, 28);
            }

            $data     = [
                'code'              => $codBarras,
                'barcode_symbology' => 'code128',
                'name'              => $product->nome,
                'type'              => 'standard',
                'external_reference' => $product->id,
                'brand'             => $this->input->post('brand'),
                'category_id'       => $category->id,
                'subcategory_id'    => $this->input->post('subcategory') ? $this->input->post('subcategory') : null,
                'cost'              => $this->sma->formatDecimal($product->valor_compra),
                'price'             => $this->sma->formatDecimal($product->valor_venda),
                'unit'              => $unity->id,
                'sale_unit'         => $this->input->post('default_sale_unit'),
                'purchase_unit'     => $this->input->post('default_purchase_unit'),
                'tax_rate'          => $this->input->post('tax_rate'),
                'tax_method'        => $this->input->post('tax_method'),
                'alert_quantity'    => $this->input->post('alert_quantity'),
                'track_quantity'    => $this->input->post('track_quantity') ? $this->input->post('track_quantity') : '0',
                'details'           => $this->input->post('details'),
                'product_details'   => $this->input->post('product_details'),
                'supplier1'         => $this->input->post('supplier'),
                'supplier1price'    => $this->sma->formatDecimal($this->input->post('supplier_price')),
                'supplier2'         => $this->input->post('supplier_2'),
                'supplier2price'    => $this->sma->formatDecimal($this->input->post('supplier_2_price')),
                'supplier3'         => $this->input->post('supplier_3'),
                'supplier3price'    => $this->sma->formatDecimal($this->input->post('supplier_3_price')),
                'supplier4'         => $this->input->post('supplier_4'),
                'supplier4price'    => $this->sma->formatDecimal($this->input->post('supplier_4_price')),
                'supplier5'         => $this->input->post('supplier_5'),
                'supplier5price'    => $this->sma->formatDecimal($this->input->post('supplier_5_price')),
                'cf1'               => $this->input->post('cf1'),
                'cf2'               => $this->input->post('cf2'),
                'cf3'               => $this->input->post('cf3'),
                'cf4'               => $this->input->post('cf4'),
                'cf5'               => $this->input->post('cf5'),
                'cf6'               => $this->input->post('cf6'),
                'promotion'         => $this->input->post('promotion'),
                'promo_price'       => $this->sma->formatDecimal($this->input->post('promo_price')),
                'start_date'        => $this->input->post('start_date') ? $this->sma->fsd($this->input->post('start_date')) : null,
                'end_date'          => $this->input->post('end_date') ? $this->sma->fsd($this->input->post('end_date')) : null,
                'supplier1_part_no' => $this->input->post('supplier_part_no'),
                'supplier2_part_no' => $this->input->post('supplier_2_part_no'),
                'supplier3_part_no' => $this->input->post('supplier_3_part_no'),
                'supplier4_part_no' => $this->input->post('supplier_4_part_no'),
                'supplier5_part_no' => $this->input->post('supplier_5_part_no'),
                'file'              => $this->input->post('file_link'),
                'slug'              => $this->input->post('slug'),
                'weight'            => $this->input->post('weight'),
                'featured'          => $this->input->post('featured'),
                'hsn_code'          => $this->input->post('hsn_code'),
                'hide'              => $this->input->post('hide') ? $this->input->post('hide') : 0,
                'second_name'       => $this->input->post('second_name'),
            ];
          
            $warehouse_qty      = null;
            $product_attributes = null;
            $this->load->library('upload');
            if ($this->input->post('type') == 'standard') {
                $wh_total_quantity = 0;
                $pv_total_quantity = 0;
                for ($s = 2; $s > 5; $s++) {
                    $data['suppliers' . $s]           = $this->input->post('supplier_' . $s);
                    $data['suppliers' . $s . 'price'] = $this->input->post('supplier_' . $s . '_price');
                }
                foreach ($warehouses as $warehouse) {
                    if ($this->input->post('wh_qty_' . $warehouse->id)) {
                        $warehouse_qty[] = [
                            'warehouse_id' => $this->input->post('wh_' . $warehouse->id),
                            'quantity'     => $this->input->post('wh_qty_' . $warehouse->id),
                            'rack'         => $this->input->post('rack_' . $warehouse->id) ? $this->input->post('rack_' . $warehouse->id) : null,
                        ];
                        $wh_total_quantity += $this->input->post('wh_qty_' . $warehouse->id);
                    }
                }

                if ($this->input->post('attributes')) {
                    $a = sizeof($_POST['attr_name']);
                    for ($r = 0; $r <= $a; $r++) {
                        if (isset($_POST['attr_name'][$r])) {
                            $product_attributes[] = [
                                'name'         => $_POST['attr_name'][$r],
                                'warehouse_id' => $_POST['attr_warehouse'][$r],
                                'quantity'     => $_POST['attr_quantity'][$r],
                                'price'        => $_POST['attr_price'][$r],
                            ];
                            $pv_total_quantity += $_POST['attr_quantity'][$r];
                        }
                    }
                } else {
                    $product_attributes = null;
                }

                if ($wh_total_quantity != $pv_total_quantity && $pv_total_quantity != 0) {
                    $this->form_validation->set_rules('wh_pr_qty_issue', 'wh_pr_qty_issue', 'required');
                    $this->form_validation->set_message('required', lang('wh_pr_qty_issue'));
                }
            }

            if ($this->input->post('type') == 'service') {
                $data['track_quantity'] = 0;
            } elseif ($this->input->post('type') == 'combo') {
                $total_price = 0;
                $c           = sizeof($_POST['combo_item_code']) - 1;
                for ($r = 0; $r <= $c; $r++) {
                    if (isset($_POST['combo_item_code'][$r]) && isset($_POST['combo_item_quantity'][$r]) && isset($_POST['combo_item_price'][$r])) {
                        $items[] = [
                            'item_code'  => $_POST['combo_item_code'][$r],
                            'quantity'   => $_POST['combo_item_quantity'][$r],
                            'unit_price' => $_POST['combo_item_price'][$r],
                        ];
                    }
                    $total_price += $_POST['combo_item_price'][$r] * $_POST['combo_item_quantity'][$r];
                }
                if ($this->sma->formatDecimal($total_price) != $this->sma->formatDecimal($this->input->post('price'))) {
                    $this->form_validation->set_rules('combo_price', 'combo_price', 'required');
                    $this->form_validation->set_message('required', lang('pprice_not_match_ciprice'));
                }
                $data['track_quantity'] = 0;
            } elseif ($this->input->post('type') == 'digital') {
                if ($_FILES['digital_file']['size'] > 0) {
                    $config['upload_path']   = $this->digital_upload_path;
                    $config['allowed_types'] = $this->digital_file_types;
                    $config['max_size']      = $this->allowed_file_size;
                    $config['overwrite']     = false;
                    $config['encrypt_name']  = true;
                    $config['max_filename']  = 25;
                    $this->upload->initialize($config);
                    if (!$this->upload->do_upload('digital_file')) {
                        $error = $this->upload->display_errors();
                        $this->session->set_flashdata('error', $error);
                        admin_redirect('products/add');
                    }
                    $file         = $this->upload->file_name;
                    $data['file'] = $file;
                } else {
                    if (!$this->input->post('file_link')) {
                        $this->form_validation->set_rules('digital_file', lang('digital_file'), 'required');
                    }
                }
                $config                 = null;
                $data['track_quantity'] = 0;
            }
            if (!isset($items)) {
                $items = null;
            }
            if ($_FILES['product_image']['size'] > 0) {
                $config['upload_path']   = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size']      = $this->allowed_file_size;
                $config['max_width']     = $this->Settings->iwidth;
                $config['max_height']    = $this->Settings->iheight;
                $config['overwrite']     = false;
                $config['max_filename']  = 25;
                $config['encrypt_name']  = true;
                $this->upload->initialize($config);
                if (!$this->upload->do_upload('product_image')) {
                    $error = $this->upload->display_errors();
                    $this->session->set_flashdata('error', $error);
                    admin_redirect('products/add');
                }
                $photo         = $this->upload->file_name;
                $data['image'] = $photo;
                $this->load->library('image_lib');
                $config['image_library']  = 'gd2';
                $config['source_image']   = $this->upload_path . $photo;
                $config['new_image']      = $this->thumbs_path . $photo;
                $config['maintain_ratio'] = true;
                $config['width']          = $this->Settings->twidth;
                $config['height']         = $this->Settings->theight;
                $this->image_lib->clear();
                $this->image_lib->initialize($config);
                if (!$this->image_lib->resize()) {
                    echo $this->image_lib->display_errors();
                }
                if ($this->Settings->watermark) {
                    $this->image_lib->clear();
                    $wm['source_image']     = $this->upload_path . $photo;
                    $wm['wm_text']          = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                    $wm['wm_type']          = 'text';
                    $wm['wm_font_path']     = 'system/fonts/texb.ttf';
                    $wm['quality']          = '100';
                    $wm['wm_font_size']     = '16';
                    $wm['wm_font_color']    = '999999';
                    $wm['wm_shadow_color']  = 'CCCCCC';
                    $wm['wm_vrt_alignment'] = 'top';
                    $wm['wm_hor_alignment'] = 'left';
                    $wm['wm_padding']       = '10';
                    $this->image_lib->initialize($wm);
                    $this->image_lib->watermark();
                }
                $this->image_lib->clear();
                $config = null;
            }

            if ($_FILES['userfile']['name'][0] != '') {
                $config['upload_path']   = $this->upload_path;
                $config['allowed_types'] = $this->image_types;
                $config['max_size']      = $this->allowed_file_size;
                $config['max_width']     = $this->Settings->iwidth;
                $config['max_height']    = $this->Settings->iheight;
                $config['overwrite']     = false;
                $config['encrypt_name']  = true;
                $config['max_filename']  = 25;
                $files                   = $_FILES;
                $cpt                     = count($_FILES['userfile']['name']);
                for ($i = 0; $i < $cpt; $i++) {
                    $_FILES['userfile']['name']     = $files['userfile']['name'][$i];
                    $_FILES['userfile']['type']     = $files['userfile']['type'][$i];
                    $_FILES['userfile']['tmp_name'] = $files['userfile']['tmp_name'][$i];
                    $_FILES['userfile']['error']    = $files['userfile']['error'][$i];
                    $_FILES['userfile']['size']     = $files['userfile']['size'][$i];

                    $this->upload->initialize($config);

                    if (!$this->upload->do_upload()) {
                        $error = $this->upload->display_errors();
                        $this->session->set_flashdata('error', $error);
                        admin_redirect('products/add');
                    } else {
                        $pho = $this->upload->file_name;

                        $photos[] = $pho;

                        $this->load->library('image_lib');
                        $config['image_library']  = 'gd2';
                        $config['source_image']   = $this->upload_path . $pho;
                        $config['new_image']      = $this->thumbs_path . $pho;
                        $config['maintain_ratio'] = true;
                        $config['width']          = $this->Settings->twidth;
                        $config['height']         = $this->Settings->theight;

                        $this->image_lib->initialize($config);

                        if (!$this->image_lib->resize()) {
                            echo $this->image_lib->display_errors();
                        }

                        if ($this->Settings->watermark) {
                            $this->image_lib->clear();
                            $wm['source_image']     = $this->upload_path . $pho;
                            $wm['wm_text']          = 'Copyright ' . date('Y') . ' - ' . $this->Settings->site_name;
                            $wm['wm_type']          = 'text';
                            $wm['wm_font_path']     = 'system/fonts/texb.ttf';
                            $wm['quality']          = '100';
                            $wm['wm_font_size']     = '16';
                            $wm['wm_font_color']    = '999999';
                            $wm['wm_shadow_color']  = 'CCCCCC';
                            $wm['wm_vrt_alignment'] = 'top';
                            $wm['wm_hor_alignment'] = 'left';
                            $wm['wm_padding']       = '10';
                            $this->image_lib->initialize($wm);
                            $this->image_lib->watermark();
                        }

                        $this->image_lib->clear();
                    }
                }
                $config = null;
            } else {
                $photos = null;
            }
            $data['quantity'] = $wh_total_quantity ?? 0;
            // $this->sma->print_arrays($data, $warehouse_qty, $product_attributes);

            $this->products_model->addProduct($data, $items, $warehouse_qty, $product_attributes, $photos);
        }
    }

    public function sendRequest()
    {
      $ch = curl_init('https://lagolimp.alliancets.app/api/appFiscal/produtos/2a12bbW4IoERhv2jhsiebu9nc83nxso20xb4nbTjexO971F');
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
      curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
      curl_setopt($ch, CURLOPT_TIMEOUT, 12);
      curl_setopt($ch, CURLOPT_HTTPHEADER, []);
      $response = curl_exec($ch);
      if($response === false){
        $response = curl_error($ch);
      }
      return $response;
    }

    public function run()
    {
        if ($m = $this->cron_model->run_cron()) {
            if ($this->input->is_cli_request()) {
                foreach ($m as $msg) {
                    echo $msg . "\n";
                }
            } else {
                echo '<!doctype html><html><head><title>Cron Job</title><style>p{background:#F5F5F5;border:1px solid #EEE; padding:15px;}</style></head><body>';
                echo '<p>' . lang('cron_finished') . '</p>';
                foreach ($m as $msg) {
                    echo '<p>' . $msg . '</p>';
                }
                echo '</body></html>';
            }
        }
    }
}