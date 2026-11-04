import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import SiteLayout from '@/layouts/site-layout';

export default function Placeholder({ title }: { title: string }) {
    return (
        <>
            <Head title={title} />
            <div className="gutter border-b border-gray-200 bg-gray-50 py-12 sm:py-20">
                <h1 className="text-3xl font-bold text-balance text-gray-900 uppercase sm:text-5xl">
                    {title}
                </h1>
            </div>
        </>
    );
}

Placeholder.layout = (page: ReactNode) => <SiteLayout>{page}</SiteLayout>;
