import { TestBed } from '@angular/core/testing';
import { AthleteTipsService } from './athlete-tips.service';
import { ApiService } from './api.service';

describe('AthleteTipsService', () => {
  let service: AthleteTipsService;
  let api: jasmine.SpyObj<ApiService>;

  beforeEach(() => {
    api = jasmine.createSpyObj<ApiService>('ApiService', ['get', 'getBlob']);

    TestBed.configureTestingModule({
      providers: [
        AthleteTipsService,
        { provide: ApiService, useValue: api },
      ],
    });

    service = TestBed.inject(AthleteTipsService);
  });

  it('loads categories from the CP5 endpoint', async () => {
    api.get.and.resolveTo({ ok: true, data: [{ key: 'general', label: 'General' }] });

    await expectAsync(service.categories()).toBeResolvedTo([{ key: 'general', label: 'General' }]);
    expect(api.get).toHaveBeenCalledWith('app/tips/categories');
  });

  it('cleans list filters and keeps pagination metadata', async () => {
    api.get.and.resolveTo({
      ok: true,
      data: [{ id: 1, title: 'Tip', type: 'tip', scope: 'global', category: { key: 'general', label: 'General' }, excerpt: 'Texto', image_url: null, published_at: null, expires_at: null, updated_at: null }],
      meta: { current_page: 2, per_page: 10, last_page: 3, total: 21 },
    });

    const result = await service.index({ q: '  movilidad  ', category: 'general', type: null, page: 2, per_page: 10 });

    expect(api.get).toHaveBeenCalledWith('app/tips', { q: 'movilidad', category: 'general', page: 2, per_page: 10 });
    expect(result.meta.total).toBe(21);
    expect(result.data[0].title).toBe('Tip');
  });

  it('loads detail and protected image bytes', async () => {
    const blob = new Blob(['image']);
    api.get.and.resolveTo({
      ok: true,
      data: {
        id: 7,
        title: 'Detalle',
        type: 'news',
        scope: 'tenant',
        category: { key: 'training', label: 'Entrenamiento' },
        excerpt: 'Detalle',
        image_url: 'http://api.test/api/v1/app/tips/7/image',
        published_at: null,
        expires_at: null,
        updated_at: null,
        body: 'Texto plano',
        content_format: 'plain_text',
      },
    });
    api.getBlob.and.resolveTo(blob);

    const detail = await service.show(7);
    const image = await service.imageBlob(7);

    expect(api.get).toHaveBeenCalledWith('app/tips/7');
    expect(api.getBlob).toHaveBeenCalledWith('app/tips/7/image');
    expect(detail.content_format).toBe('plain_text');
    expect(image).toBe(blob);
  });
});
