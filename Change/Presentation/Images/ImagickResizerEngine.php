<?php
/**
 * Copyright (C) 2014 Proximis
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */
namespace Change\Presentation\Images;

/**
 * @name \Change\Presentation\Images\ImagickResizerEngine
 */
class ImagickResizerEngine
{
	/**
	 * @param $path
	 * @return array
	 */
	public function getImageSize($path)
	{
		$imagik = new \Imagick();
		$blob = file_get_contents($path);
		$imagik->readImageBlob($blob);
		$returnValue = $imagik->valid() ? ['width' => $imagik->getImageWidth(), 'height' => $imagik->getImageHeight()] : ['height' => null, 'width' => null];
		return $returnValue;
	}

	/**
	 * @param string $inputFileName
	 * @param string $formattedFileName
	 * @param integer $maxWidth
	 * @param integer $maxHeight
	 */
	public function resize($inputFileName, $formattedFileName, $maxWidth, $maxHeight)
	{
		$imagik = new \Imagick();
		$blob = file_get_contents($inputFileName);
		$imagik->readImageBlob($blob);
		if (!$imagik->valid())
		{
			copy($inputFileName, $formattedFileName);
			return;
		}
		$origWidth = $imagik->getImageWidth();
		$origHeight = $imagik->getImageHeight();
		list ($width, $height) = $this->computeImageSize($origWidth, $origHeight, $maxWidth, $maxHeight);
		if ($width == $origWidth && $height == $origHeight)
		{
			copy($inputFileName, $formattedFileName);
			return;
		}
		if ($imagik->getNumberImages() > 1)
		{
			$imagik = $imagik->coalesceImages();
			foreach ($imagik as $frame)
			{
				$frame->thumbnailImage($width, $height, true);
				$frame->setImagePage($width, $height, 0, 0);
			}
			file_put_contents($formattedFileName, $imagik);
		}
		else
		{
			$imagik->thumbnailImage($width, $height, true);
			file_put_contents($formattedFileName, $imagik);
		}
	}

	/**
	 * @param $originalWidth
	 * @param $originalHeight
	 * @param $maxWidth
	 * @param $maxHeight
	 * @return array
	 */
	protected function computeImageSize($originalWidth, $originalHeight, $maxWidth, $maxHeight)
	{
		$resourceWidth = $originalWidth;
		$resourceHeight = $originalHeight;
		if ($maxWidth && ($originalWidth > $maxWidth))
		{
			$resourceWidth = $maxWidth;
			$resourceHeight = $resourceWidth * $originalHeight / $originalWidth;
		}

		if ($maxHeight && ($resourceHeight > $maxHeight))
		{
			$resourceHeight = $maxHeight;
			$resourceWidth = $resourceHeight * $originalWidth / $originalHeight;
		}
		$resourceWidth = round($resourceWidth);
		$resourceHeight = round($resourceHeight);
		return array(min($resourceWidth, $originalWidth), min($resourceHeight, $originalHeight));
	}
}