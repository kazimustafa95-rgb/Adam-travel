<?php

namespace App\Services\PublicApi\Evidence;

final class LocationEvidence
{
    /**
     * @param  list<string>  $analysisImages
     * @param  array<string, mixed>  $rawMetadata
     * @param  array<string, mixed>  $analysisDebug
     */
    public function __construct(
        public readonly string $platform,
        public readonly string $mediaType,
        public readonly string $title,
        public readonly string $description,
        public readonly string $pageText,
        public readonly string $transcript = '',
        public readonly string $metadataImage = '',
        public readonly array $analysisImages = [],
        public readonly array $rawMetadata = [],
        public readonly array $analysisDebug = [],
    ) {}

    public static function fromManualText(string $input): self
    {
        return new self(
            platform: 'manual_text',
            mediaType: 'text',
            title: $input,
            description: '',
            pageText: '',
        );
    }

    public function isVideo(): bool
    {
        return $this->mediaType === 'video';
    }

    /**
     * @param  list<string>  $images
     */
    public function withAdditionalAnalysisImages(array $images): self
    {
        return new self(
            platform: $this->platform,
            mediaType: $this->mediaType,
            title: $this->title,
            description: $this->description,
            pageText: $this->pageText,
            transcript: $this->transcript,
            metadataImage: $this->metadataImage,
            analysisImages: $this->uniqueStrings([...$this->analysisImages, ...$images]),
            rawMetadata: $this->rawMetadata,
            analysisDebug: array_replace_recursive($this->analysisDebug, [
                'analysis_images_collected' => count($this->uniqueStrings([...$this->analysisImages, ...$images])),
            ]),
        );
    }

    public function withTranscript(string $transcript): self
    {
        return new self(
            platform: $this->platform,
            mediaType: $this->mediaType,
            title: $this->title,
            description: $this->description,
            pageText: $this->pageText,
            transcript: trim($transcript),
            metadataImage: $this->metadataImage,
            analysisImages: $this->analysisImages,
            rawMetadata: $this->rawMetadata,
            analysisDebug: array_replace_recursive($this->analysisDebug, [
                'transcript_available' => trim($transcript) !== '',
                'transcript_length' => strlen(trim($transcript)),
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $analysisDebug
     */
    public function withAnalysisDebug(array $analysisDebug): self
    {
        return new self(
            platform: $this->platform,
            mediaType: $this->mediaType,
            title: $this->title,
            description: $this->description,
            pageText: $this->pageText,
            transcript: $this->transcript,
            metadataImage: $this->metadataImage,
            analysisImages: $this->analysisImages,
            rawMetadata: $this->rawMetadata,
            analysisDebug: array_replace_recursive($this->analysisDebug, $analysisDebug),
        );
    }

    /**
     * @return array<string, string>
     */
    public function toResponseMetadata(): array
    {
        return [
            'platform' => $this->platform,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->metadataImage,
            'pageText' => $this->pageText,
        ];
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function uniqueStrings(array $values): array
    {
        $unique = [];

        foreach ($values as $value) {
            $trimmed = trim($value);

            if ($trimmed === '' || in_array($trimmed, $unique, true)) {
                continue;
            }

            $unique[] = $trimmed;
        }

        return $unique;
    }
}
