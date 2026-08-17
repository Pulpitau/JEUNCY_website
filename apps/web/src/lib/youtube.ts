// Reconnait les liens YouTube courants (watch/short/deja-embed) pour les
// transformer en URL d'embed — renvoie null pour tout autre lien (Vimeo,
// Loom, etc.), affiche alors comme simple lien plutot qu'en iframe.
export function getYoutubeEmbedUrl(url: string): string | null {
  let parsed: URL;
  try {
    parsed = new URL(url);
  } catch {
    return null;
  }

  const host = parsed.hostname.replace(/^www\.|^m\./, '');

  if (host === 'youtu.be') {
    const id = parsed.pathname.slice(1);
    return id ? `https://www.youtube.com/embed/${id}` : null;
  }

  if (host === 'youtube.com') {
    if (parsed.pathname === '/watch') {
      const id = parsed.searchParams.get('v');
      return id ? `https://www.youtube.com/embed/${id}` : null;
    }
    const shortsMatch = parsed.pathname.match(/^\/shorts\/([\w-]+)/);
    if (shortsMatch) return `https://www.youtube.com/embed/${shortsMatch[1]}`;
    if (/^\/embed\/[\w-]+/.test(parsed.pathname)) return url;
  }

  return null;
}
