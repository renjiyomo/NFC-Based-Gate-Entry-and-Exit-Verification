import test from 'node:test';
import assert from 'node:assert/strict';
import {photoError} from '../../resources/js/lib/photos.ts';

test('photo preflight accepts supported formats through the two-megabyte boundary', () => {
    for (const type of ['image/jpeg','image/png','image/webp']) {
        assert.equal(photoError({type,size:1}),null);
        assert.equal(photoError({type,size:2*1024*1024}),null);
    }
});
test('photo preflight rejects unsupported and empty files before replacing the preview', () => {
    for (const type of ['image/svg+xml','image/gif','text/plain','']) assert.match(photoError({type,size:500}),/JPG, PNG or WebP/);
    assert.match(photoError({type:'image/png',size:0}),/empty/);
});
test('photo preflight rejects an image above the upload limit', () => {
    assert.match(photoError({type:'image/jpeg',size:2*1024*1024+1}),/2 MB/);
});
