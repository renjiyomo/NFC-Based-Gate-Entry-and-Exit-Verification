export function photoError(file: Pick<File, 'type' | 'size'>): string | null {
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type))
        return 'Choose a JPG, PNG or WebP image.';
    if (file.size > 2 * 1024 * 1024) return 'Choose an image smaller than 2 MB.';
    if (file.size === 0) return 'This image is empty. Choose another file.';
    return null;
}
