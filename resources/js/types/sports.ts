export type Coach = {
    key: string;
    name: string;
    role: string;
    solo: boolean;
    gradient: string;
    photo?: string | null;
    bio: string;
};

/** A club sharing the hall, and the key of its block on the location page. */
export type HallSharer = {
    name: string;
    key: string;
};

export type ScheduleSlot = {
    time: string;
    group: string;
    coach: string;
    /** A slot no club on this block runs — another club has the hall then. */
    foreign: boolean;
    /** Other clubs training in the same hall, same sport, same interval. */
    otherClubs: HallSharer[];
};

export type ScheduleDay = {
    day: string;
    slots: ScheduleSlot[];
};

export type TrustChip = {
    label: string;
    solo: boolean;
};

export type LocationClub = {
    key: string;
    slug: string;
    sport: string;
    name: string;
    representative: string;
    about: string;
    photos: string[];
    trustChips: TrustChip[];
    ages: string[];
    /** How far along the groups are — a separate axis from who they are for. */
    levels: string[];
    coaches: Coach[];
    schedule: ScheduleDay[];
    contactName: string;
    contactPhone: string | null;
};

export type SportOption = {
    key: string;
    label: string;
    icon: string;
    color: string | null;
    clubCount: number;
};

export type Facility = {
    icon: string;
    label: string;
};

/** One interval of a space's opening hours, as a visitor reads it. */
export type SpaceInterval = {
    start: string;
    end: string;
    price: number | null;
};

/** A pool, a pitch, a court — one of the things you can use at a location. */
export type SpaceOffer = {
    id: number;
    name: string;
    operator: string | null;
    unmanaged: boolean;
    price: string | null;
    priceNotes: string | null;
    isFree: boolean;
    capacity: number | null;
    isIndoor: boolean | null;
    hasFloodlights: boolean | null;
    surface: string | null;
    openNow: boolean;
    closesAt: string | null;
    lastVerified: string | null;
    today: SpaceInterval[];
    week: { day: string; hours: string }[];
};

/**
 * A way into a sport at this location: a club's programme, walking in, or
 * booking the whole space. Only the ones that exist here are sent.
 */
export type WayIn = {
    key: 'organizat' | 'liber' | 'inchiriere';
    verb: string;
    how: string;
    price: string | null;
    who: string;
    spaces: SpaceOffer[];
};

/** Today at this location, one row per space. Belongs to the place, not an offer. */
export type LocationDay = {
    label: string;
    from: number;
    to: number;
    rows: {
        name: string;
        sub: string;
        bars: { start: number; end: number; label: string }[];
    }[];
};

/**
 * Something paid you can get at a place that is not a sport: the sauna you buy a
 * ticket for, the massage somebody gives you. One shape for both, because a
 * visitor does not care whether they are buying a space or somebody's time.
 */
export type Extra = {
    key: string;
    icon: string;
    name: string;
    detail: string | null;
    meta: string | null;
    by: string | null;
};

export type LocationDetail = {
    slug: string;
    name: string;
    address: string;
    city: string;
    lat: number | null;
    lng: number | null;
    facilities: Facility[];
    sports: SportOption[];
    clubs: LocationClub[];
    ways: Record<string, WayIn[]>;
    extras: Extra[];
    day: LocationDay | null;
};
