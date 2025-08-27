/**
 * Top Games — Inspector controls + in-editor React preview (for CPT "game").
 * - Search games; select up to 2 (enforced)
 * - If none selected: show N latest (N = items, default 1) with optional offset
 * - Variant controls image+title size; excerpt only in 'featured'
 * - Heading text shown once above the list
 */
import { __, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
  PanelBody,
  TextControl,
  Button,
  CheckboxControl,
  Spinner,
  Notice,
  RangeControl,
  ToggleControl,
  SelectControl,
} from '@wordpress/components';
import { useEffect, useState, useRef } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import './editor.scss';

export default function Edit( { attributes, setAttributes } ) {
  const DEBUG = false;

  const {
    selected = [],
    items = 1,             // fallback count (clamped 1–2)
    offset,                // may be undefined; treat as 0 in preview
    showThumbs = true,
    variant = 'standard',  // "standard" | "featured"
    titleSize,             // optional override: sm|md|lg|xl|2xl|3xl
    headingText = '',      // shown once above the list
  } = attributes;

  const itemsClamped = Math.max(1, Math.min(2, items || 1));
  const offsetVal = Number.isInteger(offset) ? offset : 0;

  // Detect REST base for CPT 'game'
  const [restBase, setRestBase] = useState('games');
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const t = await apiFetch({ path: '/wp/v2/types/game' });
        if (!cancelled && t?.rest_base) setRestBase(t.rest_base);
      } catch {}
    })();
    return () => { cancelled = true; };
  }, []);

  // Sidebar: search + options
  const [search, setSearch]   = useState('');
  const [results, setResults] = useState([]);  // [{id,label}]
  const [loading, setLoading] = useState(false);
  const [error, setError]     = useState(null);
  const [limitMsg, setLimitMsg] = useState('');

  // Preview state
  const [previewGames, setPreviewGames] = useState([]);

  // Debounce + abort for options list
  const debounceRef = useRef(null);
  const optionsAbortRef = useRef(null);

  // Load recent options initially
  useEffect(() => {
    let cancelled = false;
    const fetchOptions = async (path) => {
      if (optionsAbortRef.current) optionsAbortRef.current.abort();
      optionsAbortRef.current = new AbortController();
      setLoading(true);
      setError(null);
      try {
        const res = await apiFetch({ path, signal: optionsAbortRef.current.signal });
        const opts = Array.isArray(res)
          ? res.map((g) => ({ id: g.id, label: g?.title?.rendered || `#${g.id}` }))
          : [];
        if (!cancelled) setResults(opts);
      } catch (e) {
        if (!cancelled && e?.name !== 'AbortError') {
          setError(e?.message || __('Failed to load games.', 'top-games'));
        }
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    fetchOptions(`/wp/v2/${restBase}?per_page=10&_fields=id,title`);
    return () => {
      cancelled = true;
      if (optionsAbortRef.current) optionsAbortRef.current.abort();
    };
  }, [restBase]);

  // Search handler (text or exact ID) — options list only
  const runSearch = async () => {
    const q = (search || '').trim();
    let path = `/wp/v2/${restBase}?per_page=10&_fields=id,title`;
    if (/^\d+$/.test(q)) {
      path = `/wp/v2/${restBase}?include=${encodeURIComponent(q)}&_fields=id,title`;
    } else if (q.length) {
      path = `/wp/v2/${restBase}?search=${encodeURIComponent(q)}&per_page=10&_fields=id,title`;
    }

    if (optionsAbortRef.current) optionsAbortRef.current.abort();
    optionsAbortRef.current = new AbortController();
    setLoading(true); setError(null);

    try {
      const res = await apiFetch({ path, signal: optionsAbortRef.current.signal });
      const opts = Array.isArray(res)
        ? res.map((g) => ({ id: g.id, label: g?.title?.rendered || `#${g.id}` }))
        : [];
      setResults(opts);
    } catch (e) {
      if (e?.name !== 'AbortError') setError(e?.message || __('Search failed.', 'top-games'));
    } finally {
      setLoading(false);
    }
  };

  const runSearchDebounced = () => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(runSearch, 250);
  };

  // Toggle selection (max 2)
  const toggle = (id) => {
    if (selected.includes(id)) {
      setAttributes({ selected: selected.filter((x) => x !== id) });
      return;
    }
    if (selected.length >= 2) {
      setLimitMsg(__('You can select up to 2 games.', 'top-games'));
      setTimeout(() => setLimitMsg(''), 1500);
      return;
    }
    setAttributes({ selected: [...selected, id] });
  };

  // Preview: selected (max 2) OR fallback (itemsClamped latest with offset)
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        let path;
        if (selected.length) {
          const pick = selected.slice(0, 2).join(',');
          path = `/wp/v2/${restBase}?include=${pick}&orderby=include&per_page=2&_embed=wp:featuredmedia,wp:term`;
        } else {
          path = `/wp/v2/${restBase}?per_page=${itemsClamped}&offset=${offsetVal}&orderby=date&order=desc&_embed=wp:featuredmedia,wp:term`;
        }
        const res = await apiFetch({ path });
        if (!cancelled) setPreviewGames(Array.isArray(res) ? res : []);
      } catch {
        if (!cancelled) setPreviewGames([]);
      }
    })();
    return () => { cancelled = true; };
  }, [selected, itemsClamped, offsetVal, restBase]);

  // Helpers
  const getThumb = (g, v = 'standard') => {
    const m = g?._embedded?.['wp:featuredmedia']?.[0];
    if (!m) return '';
    const s = m?.media_details?.sizes || {};
    if (v === 'featured') {
      return (
        s?.large?.source_url ||
        s?.full?.source_url ||
        s?.medium_large?.source_url ||
        s?.medium?.source_url ||
        m?.source_url || ''
      );
    }
    return (
      s?.medium_large?.source_url ||
      s?.medium?.source_url ||
      m?.source_url ||
      s?.thumbnail?.source_url ||
      ''
    );
  };

  const getFirstGenre = (g) => {
    const groups = g?._embedded?.['wp:term'];
    if (!Array.isArray(groups)) return null;
    for (const group of groups) {
      if (Array.isArray(group)) {
        const genre = group.find((t) => t?.taxonomy === 'genre');
        if (genre) return genre;
      }
    }
    return null;
  };

  const stripHTML = (html) => (html || '').replace(/<[^>]+>/g, '');

  const mapTitleSize = (v, override) => {
    if (override) {
      return ({
        sm: '1rem',
        md: '1.125rem',
        lg: '1.25rem',
        xl: '1.5rem',
        '2xl': '2rem',
        '3xl': '2.5rem',
      }[override] || '1.25rem');
    }
    return v === 'featured' ? '2.25rem' : '1.25rem';
  };

  const imageStyleFor = (v) =>
    v === 'featured'
      ? { maxHeight: 420, aspectRatio: '16/9', objectFit: 'cover', borderRadius: 6 }
      : { maxHeight: 220, aspectRatio: '16/9', objectFit: 'cover', borderRadius: 6 };

  const blockProps = useBlockProps({
    className: `top-games ${variant === 'featured' ? 'is-variant-featured' : 'is-variant-standard'}`
  });

  return (
    <div {...blockProps}>
      <InspectorControls>
        {/* === Choose games === */}
        <PanelBody title={ __('Top Games – Choose up to 2 games', 'top-games') } initialOpen>
          <TextControl
            label={ __('Search games (text or exact ID)', 'top-games') }
            value={search}
            onChange={(v) => { setSearch(v); runSearchDebounced(); }}
            placeholder={ __('e.g. “Zelda” or 123', 'top-games') }
            onKeyDown={(e) => { if (e.key === 'Enter') runSearch(); }}
          />
          <div style={{ display:'flex', gap:8, marginBottom:12 }}>
            <Button variant="primary" onClick={runSearch}>{ __('Search', 'top-games') }</Button>
            <Button variant="secondary" onClick={() => { setSearch(''); runSearch(); }}>
              { __('Show recent', 'top-games') }
            </Button>
            <Button variant="tertiary" onClick={() => setAttributes({ selected: [] })} disabled={!selected.length}>
              { __('Clear selection', 'top-games') }
            </Button>
          </div>

          <div aria-live="polite">
            {loading && <Spinner/>}
            {error && <Notice status="error" isDismissible={false}>{error}</Notice>}
            {limitMsg && <Notice status="info" isDismissible={false}>{limitMsg}</Notice>}
          </div>

          {!loading && !error && results.length > 0 && (
            <div style={{ maxHeight:240, overflow:'auto', border:'1px solid #e2e2e2', padding:8, borderRadius:4 }}>
              {results.map(r => (
                <CheckboxControl
                  key={r.id}
                  label={`${r.label} (ID ${r.id})`}
                  checked={selected.includes(r.id)}
                  disabled={!selected.includes(r.id) && selected.length >= 2}
                  onChange={() => toggle(r.id)}
                />
              ))}
            </div>
          )}
        </PanelBody>

        {/* === Fallback settings === */}
        <PanelBody title={ __('Fallback settings', 'top-games') } initialOpen={ false }>
          <RangeControl
            label={ __('Number of items when none selected (1–2)', 'top-games') }
            value={ itemsClamped }
            onChange={(v) => setAttributes({ items: Math.max(1, Math.min(2, v ?? 1)) })}
            min={1}
            max={2}
          />
          <RangeControl
            label={ __('Offset when none selected', 'top-games') }
            value={ Number.isInteger(offset) ? offset : 0 }
            onChange={(v) => setAttributes({ offset: Math.max(0, v ?? 0) })}
            min={0}
            max={50}
          />
          <div style={{display:'flex', gap:8}}>
            <Button variant="secondary" onClick={() => setAttributes({ offset: 0 })}>
              { __('Set offset to 0', 'top-games') }
            </Button>
            <Button variant="tertiary" onClick={() => setAttributes({ offset: undefined })}>
              { __('Unset offset', 'top-games') }
            </Button>
          </div>
        </PanelBody>

        {/* === Display === */}
        <PanelBody title={ __('Display', 'top-games') } initialOpen={ false }>
          <ToggleControl
            label={ __('Show thumbnails', 'top-games') }
            checked={ !!showThumbs }
            onChange={(val) => setAttributes({ showThumbs: !!val })}
          />
          <SelectControl
            label={ __('Style variant', 'top-games') }
            value={ variant }
            options={[
              { label: __('Standard', 'top-games'), value: 'standard' },
              { label: __('Featured (big headline)', 'top-games'), value: 'featured' },
            ]}
            onChange={(v) => setAttributes({ variant: v })}
          />
          <SelectControl
            label={ __('Title size (optional override)', 'top-games') }
            value={ titleSize || '' }
            options={[
              { label: __('Default for variant', 'top-games'), value: '' },
              { label: 'sm', value: 'sm' },
              { label: 'md', value: 'md' },
              { label: 'lg', value: 'lg' },
              { label: 'xl', value: 'xl' },
              { label: '2xl', value: '2xl' },
              { label: '3xl', value: '3xl' }
            ]}
            onChange={(v) => setAttributes({ titleSize: v || undefined })}
          />
          <TextControl
            label={ __('Heading text above block', 'top-games') }
            value={ headingText }
            onChange={(v) => setAttributes({ headingText: v })}
            placeholder={ __('e.g. Top Stories', 'top-games') }
          />
        </PanelBody>
      </InspectorControls>

      {/* In-editor preview */}
      <div style={{ display:'grid', gap:12 }}>
        {headingText ? (
          <div style={{ fontSize:'1rem', fontWeight:600, marginBottom:12 }}>
            {headingText}
          </div>
        ) : null}

        <div style={{ opacity:.75, fontStyle:'italic' }}>
          {selected.length
            ? __('Previewing your selected games (max 2).', 'top-games')
            : sprintf(__('Previewing %d latest game(s) with offset %d.', 'top-games'), itemsClamped, offsetVal)}
        </div>

        {previewGames.length ? (
          <ul style={{ listStyle:'none', padding:0, margin:0 }}>
            {previewGames.map((g) => {
              const thumb = getThumb(g, variant);
              const genre = getFirstGenre(g);
              const titleHTML = g?.title?.rendered || `#${g.id}`;
              const titleText = stripHTML(titleHTML);
              const textExcerpt = g?.excerpt?.rendered ? stripHTML(g.excerpt.rendered) : '';

              return (
                <li
                  key={g.id}
                  style={{
                    display:'flex',
                    flexDirection:'column',
                    gap:8,
                    padding:'12px 0',
                    borderBottom:'1px solid #eee',
                  }}
                >
                  {/* Image */}
                  {showThumbs && thumb ? (
                    <img
                      src={thumb}
                      alt={titleText}
                      onError={(e) => { e.currentTarget.style.display = 'none'; }}
                      style={{ width:'100%', height:'auto', ...imageStyleFor(variant) }}
                    />
                  ) : null}

                  {/* Genre */}
                  {genre ? (
                    <div style={{ fontSize:'0.75rem', fontWeight:700, opacity:0.8 }}>
                      {genre.name}
                    </div>
                  ) : null}

                  {/* Title */}
                  <h3
                    style={{
                      margin:0,
                      fontSize: mapTitleSize(variant, titleSize),
                      lineHeight: 1.15,
                    }}
                    dangerouslySetInnerHTML={{ __html: titleHTML }}
                  />

                  {/* Excerpt only for Featured */}
                  {variant === 'featured' && textExcerpt ? (
                    <p style={{ margin:0, fontSize:'0.98rem', lineHeight:1.5, opacity:0.9 }}>
                      {textExcerpt}
                    </p>
                  ) : null}
                </li>
              );
            })}
          </ul>
        ) : (
          <div style={{ opacity:.7 }}>{ __('No games found for preview.', 'top-games') }</div>
        )}
      </div>
    </div>
  );
}
