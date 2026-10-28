export default function CategoryIcon({ icon }: { icon: string }) {
    return <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false" className="size-14">
        {icon === 'clothing' ? <>
            <path d="m23 11-14 8 7 13 7-4v25h18V28l7 4 7-13-14-8c-1 8-17 8-18 0Z" />
            <path d="M28 31h8m-8 6h8m-4 6v4M23 11c2 13 16 13 18 0" />
        </> : icon === 'bag' ? <>
            <path d="M15 24h34l4 29H11l4-29Z" /><path d="M23 27V18a9 9 0 0 1 18 0v9" />
            <path d="m23 39 9-7 9 7-9 7-9-7Z" /><path d="M20 49h24" />
        </> : icon === 'jewelry' ? <>
            <path d="M16 15c-7 24 0 34 16 34s23-10 16-34M18 11l-2 4m30-4 2 4" />
            <circle cx="17" cy="26" r="3" /><circle cx="20" cy="37" r="3" /><circle cx="47" cy="26" r="3" /><circle cx="44" cy="37" r="3" />
            <path d="m32 41 6 8-6 9-6-9 6-8Z" />
        </> : <>
            <rect x="12" y="12" width="16" height="16" rx="3" /><rect x="36" y="12" width="16" height="16" rx="3" />
            <rect x="12" y="36" width="16" height="16" rx="3" /><path d="m44 34 10 10-10 10-10-10 10-10Z" />
        </>}
    </svg>;
}
