export type Coach = {
    key: string;
    name: string;
    role: string;
    solo: boolean;
    gradient: string;
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
    slug: string;
    sport: string;
    name: string;
    representative: string;
    about: string;
    photos: number;
    trustChips: TrustChip[];
    ages: string[];
    coaches: Coach[];
    schedule: ScheduleDay[];
    contactName: string;
    contactPhone: string;
};

export type SportOption = {
    key: string;
    label: string;
    icon: string;
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
    distance: string;
    gallery: string[];
    facilities: Facility[];
    sports: SportOption[];
    clubs: LocationClub[];
};
