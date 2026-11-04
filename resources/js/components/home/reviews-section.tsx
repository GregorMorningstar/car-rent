import SectionHeading from './section-heading';
import type { Review } from './data';

function Stars() {
    return (
        <div className="flex gap-0.5 text-amber-400" role="img" aria-label="Ocena 5 na 5">
            {Array.from({ length: 5 }, (_, index) => (
                <svg
                    key={index}
                    className="size-4"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path d="M10 1.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L10 14.9l-5.2 2.7 1-5.8L1.5 7.7l5.9-.9L10 1.5Z" />
                </svg>
            ))}
        </div>
    );
}

export default function ReviewsSection({ reviews }: { reviews: Review[] }) {
    return (
        <section className="gutter py-14 sm:py-20">
            <SectionHeading
                eyebrow="Opinie z Google"
                title="Co mówią nasi klienci"
                align="center"
            />

            {/* Na telefonie przewijane w poziomie, od md siatka. */}
            <ul className="scrollbar-none -mx-4 mt-8 flex snap-x snap-mandatory scroll-px-4 gap-4 overflow-x-auto px-4 pb-2 sm:-mx-6 sm:scroll-px-6 sm:px-6 md:mx-0 md:mt-10 md:grid md:grid-cols-3 md:gap-6 md:overflow-visible md:px-0 md:pb-0">
                {reviews.map((review) => (
                    <li
                        key={review.author}
                        className="flex w-[85%] shrink-0 snap-start flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 md:w-auto"
                    >
                        <Stars />
                        <blockquote className="flex-1 text-pretty text-gray-700">
                            „{review.text}”
                        </blockquote>
                        <p className="flex items-center gap-3 text-sm font-semibold text-gray-900">
                            <span className="flex size-9 items-center justify-center rounded-full bg-gray-900 text-xs text-white">
                                {review.author[0]}
                            </span>
                            {review.author}
                        </p>
                    </li>
                ))}
            </ul>
        </section>
    );
}
