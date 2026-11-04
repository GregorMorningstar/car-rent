import type { ReactNode } from 'react';

export default function PageHeader({
    title,
    description,
    action,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between lg:mb-8">
            <div>
                <h1 className="text-2xl font-bold text-balance text-gray-900 sm:text-3xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-pretty text-gray-600">{description}</p>
                )}
            </div>
            {action}
        </div>
    );
}
