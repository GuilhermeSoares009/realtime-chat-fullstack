export function avatarUrl(seedOrUser, size = 80, set = 'set1') {
  const base = process.env.NEXT_PUBLIC_ROBOHASH_URL || 'https://robohash.org';

  let seed = 'user';
  if (!seedOrUser) {
    seed = 'user';
  } else if (typeof seedOrUser === 'string') {
    seed = seedOrUser;
  } else if (typeof seedOrUser === 'object') {
    seed = seedOrUser.id ?? seedOrUser._id ?? seedOrUser.email ?? seedOrUser.username ?? seedOrUser.name ?? 'user';
  }

  const encoded = encodeURIComponent(String(seed));
  return `${base}/${encoded}?size=${size}x${size}&set=${encodeURIComponent(set)}`;
}
