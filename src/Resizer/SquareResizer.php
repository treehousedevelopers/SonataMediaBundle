<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Resizer;

use Gaufrette\File;
use Imagine\Image\Box;
use Imagine\Image\ImagineInterface;
use Imagine\Image\Point;
use Sonata\MediaBundle\Metadata\MetadataBuilderInterface;
use Sonata\MediaBundle\Model\MediaInterface;

/**
 * This reziser crop the image when the width and height are specified.
 * Every time you specify the W and H, the script generate a square with the
 * smaller size. For example, if width is 100 and height 80; the generated image
 * will be 80x80.
 *
 * @author Edwin Ibarra <edwines@feniaz.com>
 */
final class SquareResizer implements ResizerInterface
{
    private ImagineInterface $adapter;

    private int $mode;

    private MetadataBuilderInterface $metadata;

    public function __construct(ImagineInterface $adapter, int $mode, MetadataBuilderInterface $metadata)
    {
        $this->adapter = $adapter;
        $this->mode = $mode;
        $this->metadata = $metadata;
    }

    public function resize(MediaInterface $media, File $in, File $out, string $format, array $settings): void
    {
        if (!isset($settings['width'])) {
            throw new \RuntimeException(sprintf(
                'Width parameter is missing in context "%s" for provider "%s"',
                $media->getContext() ?? '',
                $media->getProviderName() ?? ''
            ));
        }

        $image = $this->adapter->load($in->getContent());
        $size = $media->getBox();

        if (null !== $settings['height']) {
            if ($size->getHeight() > $size->getWidth()) {
                $higher = $size->getHeight();
                $lower = $size->getWidth();
            } else {
                $higher = $size->getWidth();
                $lower = $size->getHeight();
            }

            $crop = $higher - $lower;

            if ($crop > 0) {
                $point = $higher === $size->getHeight() ? new Point(0, 0) : new Point((int) ($crop / 2), 0);
                $image->crop($point, new Box($lower, $lower));
                $size = $image->getSize();
            }
        }

        $settings['height'] = (int) ($settings['width'] * $size->getHeight() / $size->getWidth());

        if ($settings['height'] < $size->getHeight() && $settings['width'] < $size->getWidth()) {
            $content = $image
                ->thumbnail(new Box($settings['width'], $settings['height']), $this->mode)
                ->get($format, ['quality' => $settings['quality']]);
        } else {
            $content = $image->get($format, ['quality' => $settings['quality']]);
        }

        $out->setContent($content, $this->metadata->get($media, $out->getName()));
    }

    public function getBox(MediaInterface $media, array $settings): Box
    {
        $size = $media->getBox();

        if (null !== $settings['height']) {
            if ($size->getHeight() > $size->getWidth()) {
                $higher = $size->getHeight();
                $lower = $size->getWidth();
            } else {
                $higher = $size->getWidth();
                $lower = $size->getHeight();
            }

            if ($higher - $lower > 0) {
                $size = new Box($lower, $lower);
            }
        }

        $settings['height'] = (int) (($settings['width'] ?? 0) * $size->getHeight() / $size->getWidth());
        $settings['width'] = $settings['width'] ?? 0;

        if ($settings['height'] < $size->getHeight() && $settings['width'] < $size->getWidth()) {
            return new Box($settings['width'], $settings['height']);
        }

        return $size;
    }
}
