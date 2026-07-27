export type Coach = {
    key: string;
    name: string;
    role: string;
    solo: boolean;
    gradient: string;
    photo?: string | null;
    bio: string;
};

export type ScheduleSlot = {
    time: string;
    group: string;
    coach: string;
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
};
