import { $, Rocet } from '@rocet/rocet';
import { integ } from '@rocet/integration';
import { RocetNode } from '@rocet/RocetNode';
import { ajax } from '../ajax';
import { Fire } from './fire';

interface GalleryPhoto {
    id: number;
    path: string;
    url: string;
    position: number;
}

function parsePhotos(raw: string): GalleryPhoto[] {
    if (!raw) return [];
    try {
        const data = JSON.parse(raw);
        return Array.isArray(data) ? data : [];
    } catch {
        return [];
    }
}

async function uploadPhoto(file: File, options: {
    entity: string;
    folder: string;
    entityId: string;
    csrf: string;
}): Promise<GalleryPhoto | null> {
    const body = new FormData();
    body.append('file', file);
    body.append('entity', options.entity);
    body.append('folder', options.folder);
    if (options.entityId) {
        body.append('entity_id', options.entityId);
    }
    body.append('csrf-token', options.csrf);

    const response = await fetch(location.href, {
        method: 'POST',
        body,
        headers: { 'form-name': 'Photo/Upload' },
    });

    const data = await response.json();
    if (data?.type === 'fire') {
        Fire.show(data);
        return null;
    }
    if (data?.type === 'photo-uploaded' && data.photo) {
        return data.photo as GalleryPhoto;
    }

    Fire.show({ text: 'Не удалось загрузить фото', status: 'error' });
    return null;
}

async function deletePhoto(id: number, csrf: string): Promise<boolean> {
    const data = await ajax.post({
        id,
        'csrf-token': csrf,
    }, { 'form-name': 'Photo/Delete' });

    if (data?.type === 'fire') {
        Fire.show(data);
        return false;
    }

    return data?.type === 'photo-deleted';
}

function buildPhotoItem(photo: GalleryPhoto, onDelete: (id: number) => void): HTMLElement {
    const item = document.createElement('div');
    item.className = 'box-item';
    item.dataset.photoId = String(photo.id);

    const img = document.createElement('img');
    img.src = photo.url;
    img.alt = '';

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn-content-trash';
    btn.title = 'Удалить';
    btn.setAttribute('aria-label', 'Удалить фото');
    btn.addEventListener('click', () => onDelete(photo.id));

    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'photos[]';
    input.value = String(photo.id);

    item.append(img, btn, input);

    return item;
}

export function photoGalleryRender(Rocet: Rocet, i: number) {
    const el = $(Rocet.Elements[i]);
    if (el.attr('render')) return;
    el.attr('render', '1');

    const entity = el.attr('data-entity') || 'catalog';
    const folder = el.attr('data-folder') || entity;
    const entityId = el.attr('data-entity-id') || '';
    const csrf = el.attr('data-csrf') || '';
    let photos = parsePhotos(el.attr('data-photos') || '[]');

    const galleryId = `photo-gallery-${i}-${Date.now()}`;
    const fileInputId = `${galleryId}-input`;

    function getFotosEl(): HTMLElement | null {
        return document.querySelector(`[data-gallery-id="${galleryId}"]`);
    }

    function redraw() {
        const fotosEl = getFotosEl();
        if (!fotosEl) return;

        fotosEl.replaceChildren();
        photos.forEach((photo) => {
            fotosEl.append(buildPhotoItem(photo, handleDelete));
        });
    }

    async function handleDelete(id: number) {
        if (!confirm('Удалить фото?')) return;

        const ok = await deletePhoto(id, csrf);
        if (!ok) return;

        photos = photos.filter((p) => p.id !== id);
        redraw();
    }

    async function handleFiles(files: FileList | null) {
        if (!files?.length) return;

        for (const file of Array.from(files)) {
            const photo = await uploadPhoto(file, { entity, folder, entityId, csrf });
            if (photo) {
                photos.push(photo);
            }
        }

        redraw();
    }

    const fileInput: RocetNode = (
        <input
            type="file"
            id={fileInputId}
            accept="image/*"
            multiple
            className="photo-gallery__input"
            onchange={(evt: Event) => {
                const input = evt.target as HTMLInputElement;
                handleFiles(input.files);
                input.value = '';
            }}
        />
    ) as RocetNode;

    setTimeout(() => redraw(), 0);

    return (
        <div className="box photo-gallery">
            <div className="box-fotos" data-gallery-id={galleryId} />
            <label className="photo-gallery__label" for={fileInputId}>
                {fileInput}
                Добавить фото
            </label>
        </div>
    ) as RocetNode;
}

$('[ui=photo-gallery]').render(photoGalleryRender);
