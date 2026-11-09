import { useEffect, useId, useRef, useState } from 'react';
import { Upload, UserRound } from 'lucide-react';
import { Button } from '../ui';
import { photoError } from '../../lib/photos';
export default function PhotoPicker({
    existing,
    file,
    removed,
    error,
    onChange,
    onRemove,
}: {
    existing: string | null;
    file: File | null;
    removed: boolean;
    error?: string;
    onChange: (file: File | null) => void;
    onRemove: (removed: boolean) => void;
}) {
    const id = useId(),
        input = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null),
        [localError, setLocalError] = useState('');
    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [file]);
    const source = preview ?? (removed ? null : existing);
    function remove() {
        onChange(null);
        onRemove(Boolean(existing));
        setLocalError('');
        if (input.current) input.current.value = '';
    }
    return (
        <section className="photo-picker" aria-labelledby={`${id}-label`}>
            <div className="photo-picker-preview">
                {source ? (
                    <img src={source} alt="Identity photo preview" />
                ) : (
                    <UserRound size={30} strokeWidth={1.4} aria-hidden="true" />
                )}
            </div>
            <div className="photo-picker-content">
                <h3 className="photo-picker-title" id={`${id}-label`}>
                    Identity photo <span>Optional</span>
                </h3>
                <p>A clear photo helps the guard recognise the person.</p>
                <input
                    ref={input}
                    id={id}
                    className="sr-only"
                    tabIndex={-1}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    aria-label="Identity photo"
                    aria-describedby={`${id}-help`}
                    onChange={(e) => {
                        const chosen = e.target.files?.[0];
                        if (!chosen) return;
                        const problem = photoError(chosen);
                        setLocalError(problem ?? '');
                        if (problem) {
                            e.target.value = '';
                            return;
                        }
                        onChange(chosen);
                        onRemove(false);
                    }}
                />
                <div className="form-actions">
                    <Button onClick={() => input.current?.click()}>
                        <Upload size={15} aria-hidden="true" />
                        {source ? 'Change photo' : 'Upload photo'}
                    </Button>
                    {source && (
                        <Button variant="quiet" onClick={remove}>
                            Remove
                        </Button>
                    )}
                    {removed && existing && (
                        <Button variant="quiet" onClick={() => onRemove(false)}>
                            Undo removal
                        </Button>
                    )}
                </div>
                <small id={`${id}-help`}>
                    {removed
                        ? 'Photo will be removed when you save.'
                        : file
                          ? file.name
                          : 'JPG, PNG or WebP · Up to 2 MB'}
                </small>
                {(localError || error) && (
                    <p className="field-error" role="alert">
                        {localError || error}
                    </p>
                )}
            </div>
        </section>
    );
}
