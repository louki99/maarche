<?php

/**
* Copyright Maarch since 2008 under licence GPLv3.
* See LICENCE.txt file at the root folder for more details.
* This file is part of Maarch software.
*
*/

/**
* @brief Watermark Controller
* @author dev@maarch.org
*/

namespace Resource\controllers;

use Attachment\models\AttachmentModel;
use Configuration\models\ConfigurationModel;
use Resource\models\ResModel;
use setasign\Fpdi\Tcpdf\Fpdi;
use SrcCore\controllers\LogsController;
use SrcCore\models\CoreConfigModel;
use SrcCore\models\ValidatorModel;

class WatermarkController
{
    public static function watermarkResource(array $args)
    {
        ValidatorModel::notEmpty($args, ['resId', 'path']);
        ValidatorModel::intVal($args, ['resId']);
        ValidatorModel::stringType($args, ['path']);

        $configuration = ConfigurationModel::getByPrivilege(['select' => ['value'], 'privilege' => 'admin_parameters_watermark']);
        if (empty($configuration)) {
            return null;
        }

        $watermark = json_decode($configuration['value'], true);
        if ($watermark['enabled'] != 'true') {
            return null;
        } elseif (empty($watermark['text'])) {
            return null;
        }

        $text = $watermark['text'];
        preg_match_all('/\[(.*?)\]/i', $watermark['text'], $matches);
        foreach ($matches[1] as $value) {
            if ($value == 'date_now') {
                $tmp = date('d-m-Y');
            } elseif ($value == 'hour_now') {
                $tmp = date('H:i');
            } else {
                $resource = ResModel::getById(['select' => [$value], 'resId' => $args['resId']]);
                $tmp = $resource[$value] ?? '';
            }
            $text = str_replace("[{$value}]", $tmp, $text);
        }

        $libPath = CoreConfigModel::getSetaSignFormFillerLibrary();
        if (!empty($libPath)) {
            require_once($libPath);

            $flattenedFile = CoreConfigModel::getTmpPath() . "tmp_file_{$GLOBALS['id']}_" .rand(). "_watermark.pdf";
            $writer = new \SetaPDF_Core_Writer_File($flattenedFile);
            $document = \SetaPDF_Core_Document::loadByFilename($args['path'], $writer);

            $formFiller = new \SetaPDF_FormFiller($document);
            $fields = $formFiller->getFields();
            $fields->flatten();
            $document->save()->finish();

            $args['path'] = $flattenedFile;
        }

        try {
            $pdf = new Fpdi('P', 'pt');
            $nbPages = $pdf->setSourceFile($args['path']);
            $pdf->setPrintHeader(false);
            for ($i = 1; $i <= $nbPages; $i++) {
                $page = $pdf->importPage($i, 'CropBox');
                $size = $pdf->getTemplateSize($page);
                $pdf->AddPage($size['orientation'], $size);
                $pdf->useImportedPage($page);
                $pdf->SetFont($watermark['font'], '', $watermark['size']);
                $pdf->SetTextColor($watermark['color'][0], $watermark['color'][1], $watermark['color'][2]);
                $pdf->SetAlpha($watermark['opacity']);
                $pdf->Rotate($watermark['angle']);
                $pdf->Text($watermark['posX'], $watermark['posY'], $text);
            }
            $fileContent = $pdf->Output('', 'S');
        } catch (\Exception $e) {
            LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'resources',
                'level'     => 'ERROR',
                'tableName' => 'res_letterbox',
                'recordId'  => $args['resId'],
                'eventType' => 'watermark',
                'eventId'   => $e->getMessage()
            ]);
            $fileContent = null;
        }

        if (!empty($flattenedFile) && is_file($flattenedFile)) {
            unlink($flattenedFile);
        }

        return $fileContent;
    }

    /**
     * @codeCoverageIgnore
     */
    public static function watermarkAttachment(array $args)
    {
        ValidatorModel::notEmpty($args, ['attachmentId', 'path']);
        ValidatorModel::intVal($args, ['attachmentId']);
        ValidatorModel::stringType($args, ['path']);

        $loadedXml = CoreConfigModel::getXmlLoaded(['path' => 'modules/attachments/xml/config.xml']);
        if (empty($loadedXml)) {
            return null;
        }

        $watermark = (array)$loadedXml->CONFIG->watermark;
        if ($watermark['enabled'] != 'true') {
            return null;
        } elseif (empty($watermark['text'])) {
            return null;
        }

        $text = $watermark['text'];
        preg_match_all('/\[(.*?)\]/i', $watermark['text'], $matches);
        foreach ($matches[1] as $value) {
            if ($value == 'date_now') {
                $tmp = date('d-m-Y');
            } elseif ($value == 'hour_now') {
                $tmp = date('H:i');
            } else {
                $attachment = AttachmentModel::getById(['select' => [$value], 'id' => $args['attachmentId']]);
                $tmp = $attachment[$value] ?? '';
            }
            $text = str_replace("[{$value}]", $tmp, $text);
        }

        $color = ['192', '192', '192']; //RGB
        if (!empty($watermark['text_color'])) {
            $rawColor = explode(',', $watermark['text_color']);
            $color = count($rawColor) == 3 ? $rawColor : $color;
        }

        $font = ['helvetica', '10']; //Familly Size
        if (!empty($watermark['font'])) {
            $rawFont = explode(',', $watermark['font']);
            $font = count($rawFont) == 2 ? $rawFont : $font;
        }

        $position = [30, 35, 0, 0.5]; //X Y Angle Opacity
        if (!empty($watermark['position'])) {
            $rawPosition = explode(',', $watermark['position']);
            $position = count($rawPosition) == 4 ? $rawPosition : $position;
        }

        $libPath = CoreConfigModel::getSetaSignFormFillerLibrary();
        if (!empty($libPath)) {
            require_once($libPath);

            $flattenedFile = CoreConfigModel::getTmpPath() . "tmp_file_{$GLOBALS['id']}_" .rand(). "_watermark.pdf";
            $writer = new \SetaPDF_Core_Writer_File($flattenedFile);
            $document = \SetaPDF_Core_Document::loadByFilename($args['path'], $writer);

            $formFiller = new \SetaPDF_FormFiller($document);
            $fields = $formFiller->getFields();
            $fields->flatten();
            $document->save()->finish();

            $args['path'] = $flattenedFile;
        }

        try {
            $pdf = new Fpdi('P', 'pt');
            $nbPages = $pdf->setSourceFile($args['path']);
            $pdf->setPrintHeader(false);
            for ($i = 1; $i <= $nbPages; $i++) {
                $page = $pdf->importPage($i, 'CropBox');
                $size = $pdf->getTemplateSize($page);
                $pdf->AddPage($size['orientation'], $size);
                $pdf->useImportedPage($page);
                $pdf->SetFont($font[0], '', $font[1]);
                $pdf->SetTextColor($color[0], $color[1], $color[2]);
                $pdf->SetAlpha($position[3]);
                $pdf->Rotate($position[2]);
                $pdf->Text($position[0], $position[1], $text);
            }
            $fileContent = $pdf->Output('', 'S');
        } catch (\Exception $e) {
            LogsController::add([
                'isTech'    => true,
                'moduleId'  => 'attachments',
                'level'     => 'ERROR',
                'tableName' => 'res_attachments',
                'recordId'  => $args['attachmentId'],
                'eventType' => 'watermark',
                'eventId'   => $e->getMessage()
            ]);
            $fileContent = null;
        }

        return $fileContent;
    }
}
